<?php

namespace TeleBot\App\Handlers;

use Exception;
use TeleBot\App\Helpers\Misc;
use TeleBot\System\BaseEvent;
use TeleBot\System\Events\Text;
use TeleBot\System\Events\Command;
use TeleBot\System\Types\InlineKeyboard;
use TeleBot\System\SessionManager;
use TeleBot\App\Services\ContextoApi;

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
        $gameId = Misc::getTodaysGameId();
        $messageId = $this->event['message']['message_id'];
        $session = SessionManager::get();
        $feedbackId = $session['feedback'] ?? null;

        $this->telegram->deleteMessage($messageId);
        if ($feedbackId) {
            $this->telegram->deleteMessage($feedbackId);
            $feedbackId = null;
        }

        /** check if game has already been played */
        $history = SessionManager::get('history') ?? [];
        $matches = !empty(array_filter($history, fn($g) => $g['id'] == $gameId));
        if (!empty($history) && !empty($matches)) {
            $this->telegram->sendMessage("You already played this game today: #$gameId");

            SessionManager::set([
                'feedback' => $this->telegram->getLastMessageId(),
                'user' => SessionManager::get('user') ?? [],
                'history' => SessionManager::get('history') ?? [],
                'game' => SessionManager::get('game') ?? [],
            ]);

            return;
        }

        SessionManager::start()->set([
            'user' => [
                'firstname' => $this->event['message']['from']['first_name'],
                'lastname' => $this->event['message']['from']['last_name'] ?? null,
                'username' => $this->event['message']['from']['username'] ?? null,
            ],
            'history' => SessionManager::get('history') ?? [],
            'feedback' => $feedbackId,
            'game' => [
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
            ],
        ]);


        $session['feedback'] = null;
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

        unset($session['state']);
        SessionManager::set($session, SessionManager::get('state'));

        $this->telegram->sendMessage(Misc::getTemplate());

        $session['message_id'] = $this->telegram->getLastMessageId();

        unset($session['state']);
        SessionManager::set($session, SessionManager::get('state'));
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
        $feedbackId = null;
        if (SessionManager::get('feedback')) {
            $this->telegram->deleteMessage(SessionManager::get('feedback'));
        }

        $messageId = $this->event['message']['message_id'];
        $this->telegram->deleteMessage($messageId);

        /** check if game is initiated */
        $isInitiated = !empty(SessionManager::get('game'));
        $playing = in_array(SessionManager::get('state'), ['started', 'playing']);
        if (!$isInitiated || !$playing) {
            $this->telegram->sendMessage('Please start a game first!');

            SessionManager::set([
                'feedback' => $this->telegram->getLastMessageId(),
                'user' => SessionManager::get('user') ?? [],
                'settings' => SessionManager::get('settings') ?? [],
                'history' => SessionManager::get('history') ?? [],
                'game' => SessionManager::get('game') ?? [],
            ]);

            return;
        }

        $tipDistance = Misc::getNextTipDistance((SessionManager::get('game.history') ?? []));
        $hint = ContextoApi::getHint(SessionManager::get('game.id'), $tipDistance);
        if ($hint) {
            SessionManager::set([
                'message_id' => SessionManager::get('message_id'),
                'user' => SessionManager::get('user'),
                'settings' => SessionManager::get('settings'),
                'history' => SessionManager::get('history') ?? [],
                'feedback' => $feedbackId,
                'game' => [
                    'id' => SessionManager::get('game.id'),
                    'guesses' => SessionManager::get('game.guesses') + 1,
                    'hints' => SessionManager::get('game.hints') + 1,
                    'distance' => $hint->distance,
                    'last_word' => $hint->word,
                    'progress' => (array)Misc::getRate($hint->distance),
                    'history' => [
                        ...(SessionManager::get('game.history') ?? []),
                        [$hint->word, $hint->distance],
                    ],
                ],
            ], 'playing');

            $this->telegram->editMessage(SessionManager::get('message_id'), Misc::getTemplate());
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
        /** delete last feedback message */
        $feedbackId = null;
        if (SessionManager::get('feedback')) {
            $this->telegram->deleteMessage(SessionManager::get('feedback'));
        }

        $messageId = $this->event['message']['message_id'];
        $this->telegram->deleteMessage($messageId);

        /** check if game is initiated */
        $isInitiated = !empty(SessionManager::get('game'));
        $playing = in_array(SessionManager::get('state'), ['started', 'playing']);
        if (!$isInitiated || !$playing) {
            $this->telegram->sendMessage('Please start a game first!');

            SessionManager::set([
                'feedback' => $this->telegram->getLastMessageId(),
                'user' => SessionManager::get('user') ?? [],
                'settings' => SessionManager::get('settings') ?? [],
                'history' => SessionManager::get('history') ?? [],
                'game' => SessionManager::get('game') ?? [],
            ]);

            return;
        }

        /** min attempts required */
        if (count(SessionManager::get('game.history')) < 3) {
            $this->telegram->sendMessage('Please try to guess at least 3 words!');

            SessionManager::set([
                'message_id' => SessionManager::get('message_id'),
                'feedback' => $this->telegram->getLastMessageId(),
                'user' => SessionManager::get('user') ?? [],
                'settings' => SessionManager::get('settings') ?? [],
                'history' => SessionManager::get('history') ?? [],
                'game' => SessionManager::get('game') ?? [],
            ]);

            return;
        }

        $answer = ContextoApi::giveUp(SessionManager::get('game.id'));
        if ($answer) {
            SessionManager::set([
                'message_id' => SessionManager::get('message_id'),
                'feedback' => $feedbackId,
                'user' => SessionManager::get('user'),
                'settings' => SessionManager::get('settings') ?? [],
                'history' => [
                    ...(SessionManager::get('history') ?? []), [
                        'id' => SessionManager::get('game.id'),
                        'status' => 'lost',
                        'date' => date('Y-m-d H:i:s A')
                    ]
                ],
                'game' => [
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
                ],
            ], 'completed');

            $this->telegram->withOptions([
                'reply_markup' => [
                    'inline_keyboard' => (new InlineKeyboard)
                        ->addButton('👎👎 You lost 👎👎', 'lost', InlineKeyboard::CALLBACK_DATA)
                        ->toArray(),
                ]
            ]);

            $this->telegram->editMessage(SessionManager::get('message_id'), Misc::getTemplate(true));
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
        /** delete last feedback message */
        $feedbackId = null;
        if (SessionManager::get('feedback')) {
            $this->telegram->deleteMessage(SessionManager::get('feedback'));
        }

        $messageId = $this->event['message']['message_id'];

        $this->telegram->deleteMessage($messageId);
        $this->telegram->deleteMessage(SessionManager::get('message_id'));

        SessionManager::set([
            'user' => SessionManager::get('user'),
            'settings' => SessionManager::get('settings'),
            'history' => SessionManager::get('history'),
        ]);
    }

    /**
     * handle incoming text message
     *
     * @return void
     * @throws Exception
     */
    #[Text(true)]
    public function word(): void
    {
        $feedbackId = SessionManager::get('feedback') ?? null;
        if ($feedbackId) {
            $this->telegram->deleteMessage($feedbackId);
            $feedbackId = null;
        }

        $word = strtolower(trim($this->event['message']['text']));
        $messageId = $this->event['message']['message_id'];
        $this->telegram->deleteMessage($messageId);

        /** check if game is initiated */
        $inGame = in_array(SessionManager::get('state'), ['started', 'playing']);
        $initiated = !empty(SessionManager::get('game'));
        if (!$initiated || !$inGame) {
            $this->telegram->sendMessage('Please start a game first!');

            SessionManager::set([
                'feedback' => $this->telegram->getLastMessageId(),
                'user' => SessionManager::get('user') ?? [],
                'settings' => SessionManager::get('settings') ?? [],
                'history' => SessionManager::get('history') ?? [],
                'game' => SessionManager::get('game') ?? [],
            ]);

            return;
        }

        /** multiple words */
        if (preg_match('/\s+/', $word)) {
            $this->telegram->sendMessage('Please try a single word at a time!');

            SessionManager::set([
                'message_id' => SessionManager::get('message_id'),
                'feedback' => $this->telegram->getLastMessageId(),
                'user' => SessionManager::get('user') ?? [],
                'settings' => SessionManager::get('settings') ?? [],
                'history' => SessionManager::get('history') ?? [],
                'game' => SessionManager::get('game') ?? [],
            ]);

            return;
        }

        $result = ContextoApi::guess(SessionManager::get('game.id'), $word);
        if ($result) {
            $history = SessionManager::get('history');
            $hasWon = $result->distance == 0;
            if ($hasWon) {
                $history[] = [
                    'id' => SessionManager::get('game.id'),
                    'status' => 'won',
                    'date' => date('Y-m-d H:i:s A')
                ];
            }

            SessionManager::set([
                'message_id' => SessionManager::get('message_id'),
                'feedback' => $feedbackId,
                'user' => SessionManager::get('user'),
                'settings' => SessionManager::get('settings'),
                'history' => $history,
                'game' => [
                    'id' => SessionManager::get('game.id'),
                    'guesses' => SessionManager::get('game.guesses') + 1,
                    'hints' => SessionManager::get('game.hints'),
                    'distance' => $result->distance,
                    'last_word' => $result->word,
                    'progress' => (array)Misc::getRate($result->distance),
                    'history' => [
                        ...(SessionManager::get('game.history') ?? []),
                        [$result->word, $result->distance],
                    ],
                ],
            ], ($hasWon ? 'completed' : 'playing'));

            if ($hasWon) {
                $this->telegram->withOptions([
                    'reply_markup' => [
                        'inline_keyboard' => (new InlineKeyboard)
                            ->addButton('🎉🎉 You won 🎉🎉', 'won', InlineKeyboard::CALLBACK_DATA)
                            ->toArray(),
                    ]
                ]);
            }

            $this->telegram->editMessage(SessionManager::get('message_id'), Misc::getTemplate($hasWon));
        }

    }
}
