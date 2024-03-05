<?php

namespace TeleBot\App\Handlers;

use Exception;
use TeleBot\App\Helpers\Misc;
use TeleBot\System\BaseEvent;
use TeleBot\System\Events\Command;
use TeleBot\System\SessionManager;
use TeleBot\System\Events\CallbackQuery;
use TeleBot\System\Types\InlineKeyboard;
use TeleBot\System\Types\IncomingCallbackQuery;

class Games extends BaseEvent
{

    /**
     * handle list command
     *
     * @return void
     * @throws Exception
     */
    #[Command('list')]
    public function list(): void
    {
        $currentGameId = $lastGameId = Misc::getTodaysGameId();
        $messageId = $this->event['message']['message_id'];
        $inlineKeyboard = new InlineKeyboard();

        $this->telegram->deleteMessage($messageId);

        for ($i = 0; $i < 9; $i++) {
            $lastGameId -= 1;
            if ($lastGameId == 0) break;
            $inlineKeyboard->addButton("#$lastGameId", ['game:id' => $lastGameId], InlineKeyboard::CALLBACK_DATA);
        }

        $this->telegram->withOptions([
            'reply_markup' => [
                'inline_keyboard' => [
                    ...$inlineKeyboard->toArray(),
                    ...(new InlineKeyboard)
                        ->addButton('<<', ['list:back' => $lastGameId], InlineKeyboard::CALLBACK_DATA)
                        ->addButton('>>', ['list:next' => ''], InlineKeyboard::CALLBACK_DATA)
                        ->toArray()
                ]
            ]
        ])->sendMessage('Choose a game to play:');

        $session = SessionManager::get();
        $session['feedback'] = $this->telegram->getLastMessageId();

        unset($session['state']);
        SessionManager::set($session, SessionManager::get('state'));
    }

    /**
     * handle next button query
     *
     * @param IncomingCallbackQuery $query
     * @return void
     * @throws Exception
     */
    #[CallbackQuery('list:next')]
    public function next(IncomingCallbackQuery $query): void
    {
        $currentGameId = Misc::getTodaysGameId();
        $lastGameId = $query('list:next');
        $feedbackId = SessionManager::get('feedback') ?? null;
        $inlineKeyboard = new InlineKeyboard();

        if (empty($feedbackId) || empty($lastGameId)) return;

        for ($i = 9; $i > 0; $i--) {
            $lastId = ($lastGameId - 1) + $i;
            $inlineKeyboard->addButton("#$lastId", ['game:id' => $lastId], InlineKeyboard::CALLBACK_DATA);
        }

        $this->telegram->withOptions([
            'reply_markup' => [
                'inline_keyboard' => [
                    ...$inlineKeyboard->toArray(),
                    ...(new InlineKeyboard)
                        ->addButton('<<', [
                            'list:back' => $lastGameId - 9
                        ], InlineKeyboard::CALLBACK_DATA)
                        ->addButton('>>', [
                            'list:next' => (int)($lastGameId >= $currentGameId ? 0 : $lastGameId)
                        ], InlineKeyboard::CALLBACK_DATA)
                        ->toArray()
                ]
            ]
        ])->editMessage($feedbackId, 'Choose a game to play:');

        $session = SessionManager::get();
        $session['feedback'] = $this->telegram->getLastMessageId();

        unset($session['state']);
        SessionManager::set($session, SessionManager::get('state'));
    }

    /**
     * handle back button query
     *
     * @param IncomingCallbackQuery $query
     * @return void
     * @throws Exception
     */
    #[CallbackQuery('list:back')]
    public function back(IncomingCallbackQuery $query): void
    {
        $lastGameId = $query('list:back');
        $feedbackId = SessionManager::get('feedback') ?? null;
        if (empty($feedbackId) || empty($lastGameId)) return;

        $inlineKeyboard = new InlineKeyboard();
        for ($i = 0; $i < 9; $i++) {
            $lastGameId -= 1;
            if ($lastGameId == 0) break;
            $inlineKeyboard->addButton("#$lastGameId", ['game:id' => $lastGameId], InlineKeyboard::CALLBACK_DATA);
        }

        $this->telegram->withOptions([
            'reply_markup' => [
                'inline_keyboard' => [
                    ...$inlineKeyboard->toArray(),
                    ...(new InlineKeyboard)
                        ->addButton('<<', ['list:back' => $lastGameId], InlineKeyboard::CALLBACK_DATA)
                        ->addButton('>>', ['list:next' => $lastGameId + 9], InlineKeyboard::CALLBACK_DATA)
                        ->toArray()
                ]
            ]
        ])->editMessage($feedbackId, 'Choose a game to play:');

        $session = SessionManager::get();
        unset($session['state']);
        SessionManager::set($session, SessionManager::get('state'));
    }

    /**
     * handle selected game query
     *
     * @param IncomingCallbackQuery $query
     * @return void
     * @throws Exception
     */
    #[CallbackQuery('games:id')]
    public function setGame(IncomingCallbackQuery $query): void
    {
        $gameId = $query('games:id');
        $feedbackId = SessionManager::get('feedback') ?? null;
        if ($feedbackId) {
            $this->telegram->deleteMessage($feedbackId);
        }

        /** start a game */
        $session = SessionManager::get();
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

        $this->telegram->sendMessage(Misc::getTemplate());
        $session['message_id'] = $this->telegram->getLastMessageId();

        unset($session['state']);
        SessionManager::set($session, SessionManager::get('state'));
    }

}