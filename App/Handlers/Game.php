<?php

namespace TeleBot\App\Handlers;

use Exception;
use TeleBot\App\Helpers\Misc;
use TeleBot\System\BaseEvent;
use TeleBot\System\Events\Text;
use TeleBot\System\Events\Command;
use TeleBot\System\SessionManager;
use TeleBot\App\Services\ContextoApi;
use TeleBot\System\Types\InlineKeyboard;

class Game extends BaseEvent
{

    /**
     * handle play command
     *
     * @return void
     * @throws Exception
     */
    #[Command('play')]
    public function play(): void
    {
        $session = SessionManager::get();
        $gameId = Misc::getTodaysGameId($session['settings']['language']);
        $messageId = $this->event['message']['message_id'];
        $feedbackId = $session['feedback'] ?? null;
        $settingsId = $session['settings']['id'] ?? null;

        $this->telegram->deleteMessage($messageId);
        if ($settingsId) {
            $this->telegram->deleteMessage($settingsId);
            $session['settings']['id'] = null;
        }

        if ($feedbackId) {
            $this->telegram->deleteMessage($feedbackId);
            $feedbackId = null;
        }

        /** check if the game has already been played */
        $history = SessionManager::get('history') ?? [];
        $matches = !empty(array_filter($history, fn($g) => $g['id'] == $gameId));
        if (!empty($history) && !empty($matches)) {
            $this->telegram->sendMessage("You already played this game today: #$gameId");
            $session['feedback'] = $this->telegram->getLastMessageId();
            SessionManager::set($session, SessionManager::get('state'));
            return;
        }

        /** check if a game is already in progress (to avoid duplicate game sessions) */
        $inGame = in_array($session['state'], ['started', 'playing']);
        $inGame = $inGame && !empty($session['game']) && !empty($session['game_session']);

        /** ignore if the same game is already in progress */
        if ($inGame && $session['game']['id'] == $gameId) return;

        $session['feedback'] = $feedbackId;
        $session['game'] = [
            'id' => $gameId,
            'guesses' => 0,
            'hints' => 0,
            'distance' => 0,
            'last_word' => 'N/A',
            'history' => [],
            'progress' => [
                'value' => 0,
                'type' => 'N'
            ],
        ];

        if ($inGame) {
            $this->telegram->editMessage($session['game_session'], Misc::getTemplate($session['game']));
        } else {
            $this->telegram->sendMessage(Misc::getTemplate($session['game']));
            $session['game_session'] = $this->telegram->getLastMessageId();
        }

        unset($session['state']);
        SessionManager::set($session);
    }

    /**
     * handle hint command
     *
     * @return void
     * @throws Exception
     */
    #[Command('hint')]
    public function hint(): void
    {
        $session = SessionManager::get();
        $feedbackId = $session['feedback'] ?? null;
        $settingsId = $session['settings']['id'] ?? null;

        if ($feedbackId) $this->telegram->deleteMessage($feedbackId);
        if ($settingsId) {
            $this->telegram->deleteMessage($settingsId);
            $session['settings']['id'] = null;
        }

        $messageId = $this->event['message']['message_id'];
        $this->telegram->deleteMessage($messageId);

        /** check if game is initiated */
        $isInitiated = !empty($session['game']);
        $playing = in_array($session['state'], ['started', 'playing']);
        if (!$isInitiated || !$playing) {
            $this->telegram->sendMessage('Please start a game first!');

            $session['feedback'] = $this->telegram->getLastMessageId();
            $session['game'] = [];

            unset($session['state']);
            SessionManager::set($session, SessionManager::get('state'));
            return;
        }

        $tipDistance = Misc::getTipDistance($session['settings']['difficulty'], ($session['game']['history'] ?? []));
        $hint = ContextoApi::getHint($session['game']['id'], $tipDistance, $session['settings']['language']);
        if ($hint) {
            $session['feedback'] = $feedbackId;
            $session['game'] = [
                'id' => $session['game']['id'],
                'guesses' => $session['game']['guesses'] + 1,
                'hints' => $session['game']['hints'] + 1,
                'distance' => $hint->distance,
                'last_word' => $hint->word,
                'progress' => (array)Misc::getRate($hint->distance),
                'history' => [
                    ...($session['game']['history'] ?? []),
                    [$hint->word, $hint->distance],
                ],
            ];

            unset($session['state']);
            SessionManager::set($session, 'playing');

            $this->telegram->editMessage($session['game_session'], Misc::getTemplate($session['game']));
        }
    }

    /**
     * handle give-up command
     *
     * @return void
     * @throws Exception
     */
    #[Command('giveup')]
    public function giveUp(): void
    {
        $session = SessionManager::get();
        $feedbackId = $session['feedback'] ?? null;
        $settingsId = $session['settings']['id'] ?? null;

        if ($feedbackId) $this->telegram->deleteMessage($feedbackId);
        if ($settingsId) {
            $this->telegram->deleteMessage($settingsId);
            $session['settings']['id'] = null;
        }

        $messageId = $this->event['message']['message_id'];
        $this->telegram->deleteMessage($messageId);

        /** check if game is initiated */
        $isInitiated = !empty($session['game']);
        $playing = in_array($session['state'], ['started', 'playing']);
        if (!$isInitiated || !$playing) {
            $this->telegram->sendMessage('Please start a game first!');

            $session['feedback'] = $this->telegram->getLastMessageId();
            $session['game'] = [];

            unset($session['state']);
            SessionManager::set($session, SessionManager::get('state'));
            return;
        }

        /** min attempts required */
        if (count($session['game']['history']) < 3) {
            $this->telegram->sendMessage('Please try to guess at least 3 words!');

            $session['feedback'] = $this->telegram->getLastMessageId();
            unset($session['state']);
            SessionManager::set($session, SessionManager::get('state'));
            return;
        }

        $answer = ContextoApi::giveUp($session['game']['id'], $session['settings']['language']);
        if ($answer) {
            $session['feedback'] = $feedbackId;
            $session['history'] = [
                ...($session['history'] ?? []), [
                    'id' => SessionManager::get('game.id'),
                    'word' => $answer->word,
                    'status' => 'lost',
                    'date' => date('Y-m-d H:i:s A')
                ]
            ];
            $session['game'] = [
                'id' => SessionManager::get('game.id'),
                'guesses' => SessionManager::get('game.guesses'),
                'hints' => SessionManager::get('game.hints'),
                'distance' => $answer->distance,
                'last_word' => $answer->word,
                'progress' => (array)Misc::getRate($answer->distance),
                'history' => [
                    ...(SessionManager::get('game.history') ?? []),
                    [$answer->word, $answer->distance],
                ],
            ];

            unset($session['state']);
            SessionManager::set($session, 'completed');

            $this->telegram->withOptions([
                'reply_markup' => [
                    'inline_keyboard' => (new InlineKeyboard)
                        ->addButton('👎👎 You lost 👎👎', 'lost', InlineKeyboard::CALLBACK_DATA)
                        ->toArray(),
                ]])->editMessage($session['game_session'], Misc::getTemplate($session['game'], true));
        }
    }

    /**
     * handle reset command
     *
     * @return void
     * @throws Exception
     */
    #[Command('reset')]
    public function reset(): void
    {
        $session = SessionManager::get();
        $feedbackId = $session['feedback'] ?? null;
        $settingsId = $session['settings']['id'] ?? null;
        $messageId = $this->event['message']['message_id'];

        if ($settingsId) $this->telegram->deleteMessage($settingsId);
        if ($feedbackId) $this->telegram->deleteMessage($feedbackId);

        $this->telegram->deleteMessage($messageId);
        $this->telegram->deleteMessage($session['game_session']);

        $session['game'] = [];
        $session['feedback'] = null;
        $session['settings']['id'] = null;
        unset($session['state']);

        SessionManager::set($session);
    }

    /**
     * handle an incoming text message
     *
     * @return void
     * @throws Exception
     */
    #[Text(true)]
    public function word(): void
    {
        $session = SessionManager::get();
        $feedbackId = $session['feedback'] ?? null;
        $settingsId = $session['settings']['id'] ?? null;

        if ($settingsId) {
            $this->telegram->deleteMessage($settingsId);
            $session['settings']['id'] = null;
        }

        if ($feedbackId) {
            $this->telegram->deleteMessage($feedbackId);
            $feedbackId = null;
        }

        $word = strtolower(trim($this->event['message']['text']));
        $messageId = $this->event['message']['message_id'];
        $this->telegram->deleteMessage($messageId);

        /** check if game is initiated */
        $inGame = in_array($session['state'], ['started', 'playing']);
        $initiated = !empty($session['game']);
        if (!$initiated || !$inGame) {
            $this->telegram->sendMessage('Please start a game first!');
            $session['feedback'] = $this->telegram->getLastMessageId();
            unset($session['state']);
            SessionManager::set($session);
            return;
        }

        /** multiple words */
        if (preg_match('/\s+/', $word)) {
            $this->telegram->sendMessage('Please try a single word at a time!');
            $session['feedback'] = $this->telegram->getLastMessageId();
            unset($session['state']);
            SessionManager::set($session, 'playing');
            return;
        }

        $result = ContextoApi::guess($session['game']['id'], $word, $session['settings']['language']);
        if ($result) {
            $history = $session['history'] ?? [];
            $hasWon = $result->distance == 0;
            if ($hasWon) {
                $history[] = [
                    'id' => $session['game']['id'],
                    'word' => $result->word,
                    'status' => 'won',
                    'date' => date('Y-m-d H:i:s A')
                ];
            }

            $session['feedback'] = $feedbackId;
            $session['history'] = $history;
            $session['game'] = [
                'id' => $session['game']['id'],
                'guesses' => $session['game']['guesses'] + 1,
                'hints' => $session['game']['hints'],
                'distance' => $result->distance,
                'last_word' => $result->word,
                'progress' => (array)Misc::getRate($result->distance),
                'history' => [
                    ...($session['game']['history'] ?? []),
                    [$result->word, $result->distance],
                ],
            ];

            unset($session['state']);
            SessionManager::set($session, ($hasWon ? 'completed' : 'playing'));

            if ($hasWon) {
                $this->telegram->withOptions([
                    'reply_markup' => [
                        'inline_keyboard' => (new InlineKeyboard)
                            ->addButton('🎉🎉 You won 🎉🎉', 'won', InlineKeyboard::CALLBACK_DATA)
                            ->toArray(),
                    ]
                ]);
            }

            $this->telegram->editMessage($session['game_session'], Misc::getTemplate($session['game'], $hasWon));
        }
    }

}
