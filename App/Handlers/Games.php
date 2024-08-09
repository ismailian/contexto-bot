<?php

namespace TeleBot\App\Handlers;

use Exception;
use TeleBot\App\Helpers\Misc;
use TeleBot\System\BaseEvent;
use TeleBot\App\Helpers\Pager;
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
        $session = SessionManager::get();
        $currentGameId = $lastGameId = Misc::getTodaysGameId($session['settings']['language']);
        $messageId = $this->event['message']['message_id'];
        $feedbackId = $session['feedback'] ?? null;
        $settingsId = $session['settings']['id'] ?? null;
        $inlineKeyboard = new InlineKeyboard();

        $this->telegram->deleteMessage($messageId);
        if ($feedbackId) $this->telegram->deleteMessage($feedbackId);
        if ($settingsId) $this->telegram->deleteMessage($settingsId);

        $pager = Pager::setLimit($currentGameId)->back(($currentGameId - 1));
        foreach ($pager->pages as $gameId) {
            $inlineKeyboard->addButton("#$gameId", ['game:id' => $gameId], InlineKeyboard::CALLBACK_DATA);
        }

        $this->telegram->withOptions([
            'reply_markup' => [
                'inline_keyboard' => [
                    ...$inlineKeyboard->toArray(),
                    ...(new InlineKeyboard)
                        ->addButton('<<', ['list:back' => $pager->back], InlineKeyboard::CALLBACK_DATA)
                        ->addButton('>>', ['list:next' => $pager->next], InlineKeyboard::CALLBACK_DATA)
                        ->toArray()
                ]
            ]
        ])->sendMessage('Choose a game to play:');

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
        $session = SessionManager::get();
        $currentGameId = Misc::getTodaysGameId($session['settings']['language']);
        $lastGameId = $query('list:next');
        $feedbackId = $session['feedback'] ?? null;
        $settingsId = $session['settings']['id'] ?? null;
        $inlineKeyboard = new InlineKeyboard();

        if (empty($feedbackId) || empty($lastGameId)) return;
        if ($settingsId) $this->telegram->deleteMessage($settingsId);

        $pager = Pager::setLimit($currentGameId)->next($lastGameId);
        foreach ($pager->pages as $gameId) {
            $inlineKeyboard->addButton("#$gameId", ['game:id' => $gameId], InlineKeyboard::CALLBACK_DATA);
        }

        $this->telegram->withOptions([
            'reply_markup' => [
                'inline_keyboard' => [
                    ...$inlineKeyboard->toArray(),
                    ...(new InlineKeyboard)
                        ->addButton('<<', ['list:back' => $pager->back], InlineKeyboard::CALLBACK_DATA)
                        ->addButton('>>', ['list:next' => $pager->next], InlineKeyboard::CALLBACK_DATA)
                        ->toArray()
                ]
            ]
        ])->editMessage($feedbackId, 'Choose a game to play:');

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
        $session = SessionManager::get();
        $currentGameId = Misc::getTodaysGameId($session['settings']['language']);
        $lastGameId = $query('list:back');
        $feedbackId = $session['feedback'] ?? null;
        $settingsId = $session['settings']['id'] ?? null;
        $inlineKeyboard = new InlineKeyboard();

        if (empty($feedbackId) || empty($lastGameId)) return;
        if ($settingsId) $this->telegram->deleteMessage($settingsId);

        $pager = Pager::setLimit($currentGameId)->back($lastGameId);
        foreach ($pager->pages as $gameId) {
            $inlineKeyboard->addButton("#$gameId", ['game:id' => $gameId], InlineKeyboard::CALLBACK_DATA);
        }

        $this->telegram->withOptions([
            'reply_markup' => [
                'inline_keyboard' => [
                    ...$inlineKeyboard->toArray(),
                    ...(new InlineKeyboard)
                        ->addButton('<<', ['list:back' => $pager->back], InlineKeyboard::CALLBACK_DATA)
                        ->addButton('>>', ['list:next' => $pager->next], InlineKeyboard::CALLBACK_DATA)
                        ->toArray()
                ]
            ]
        ])->editMessage($feedbackId, 'Choose a game to play:');

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
    #[CallbackQuery('game:id')]
    public function setGame(IncomingCallbackQuery $query): void
    {
        $gameId = $query('game:id');
        $session = SessionManager::get();

        $feedbackId = $session['feedback'] ?? null;
        if ($feedbackId) $this->telegram->deleteMessage($feedbackId);

        $inGame = in_array($session['state'], ['started', 'playing']);
        $inGame = $inGame && !empty($session['game']) && !empty($session['game_session']);
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
            $this->telegram->editMessage($session['game_session'], Misc::getTemplate($session['game'], false, true));
        } else {
            $this->telegram->sendMessage(Misc::getTemplate($session['game'], false, true));
            $session['game_session'] = $this->telegram->getLastMessageId();
        }

        unset($session['state']);
        SessionManager::set($session);
    }

}
