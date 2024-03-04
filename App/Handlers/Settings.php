<?php

namespace TeleBot\App\Handlers;

use Exception;
use TeleBot\System\BaseEvent;
use TeleBot\System\Events\Command;
use TeleBot\System\SessionManager;
use TeleBot\System\Events\CallbackQuery;
use TeleBot\System\Types\InlineKeyboard;
use TeleBot\System\Types\IncomingCallbackQuery;

class Settings extends BaseEvent
{

    /**
     * handle settings command
     *
     * @return void
     * @throws Exception
     */
    #[Command('settings')]
    public function settings(): void
    {
        $messageId = $this->event['message']['message_id'];
        $feedbackId = SessionManager::get('feedback') ?? null;

        $this->telegram->deleteMessage($messageId);
        if ($feedbackId) {
            $this->telegram->deleteMessage($feedbackId);
        }

        $this->telegram->withOptions([
            'reply_markup' => [
                'inline_keyboard' => (new InlineKeyboard)
                    ->addButton('Language', ['settings' => 'language'], InlineKeyboard::CALLBACK_DATA)
                    ->addButton('Difficulty', ['settings' => 'difficulty'], InlineKeyboard::CALLBACK_DATA)
                    ->toArray()
            ]
        ])->sendMessage('Choose settings to customize:');

        $session = SessionManager::get();
        $session['settings']['id'] = $this->telegram->getLastMessageId();
        unset($session['state']);

        SessionManager::set($session, SessionManager::get('state'));
    }

    /**
     * handle language settings
     *
     * @param IncomingCallbackQuery $query
     * @return void
     * @throws Exception
     */
    #[CallbackQuery('settings', 'language')]
    public function language(IncomingCallbackQuery $query): void
    {
        $settingsId = SessionManager::get('settings.id');
        $lang = SessionManager::get('settings.language');
        $isLang = fn($l) => $l == $lang ? ' ✅' : '';

        if (empty($settingsId)) return;

        $this->telegram->withOptions([
            'reply_markup' => [
                'inline_keyboard' => (new InlineKeyboard)
                    ->setRowMax(2)
                    ->addButton('English' . $isLang('en'), ['settings:lang' => 'en'], InlineKeyboard::CALLBACK_DATA)
                    ->addButton('Spanish' . $isLang('es'), ['settings:lang' => 'es'], InlineKeyboard::CALLBACK_DATA)
                    ->addButton('Portuguese' . $isLang('pt'), ['settings:lang' => 'pt'], InlineKeyboard::CALLBACK_DATA)
                    ->toArray(),
            ]
        ])->editMessage($settingsId, 'Choose your language:');
    }

    /**
     * handle difficulty settings
     *
     * @param IncomingCallbackQuery $query
     * @return void
     * @throws Exception
     */
    #[CallbackQuery('settings', 'difficulty')]
    public function difficulty(IncomingCallbackQuery $query): void
    {
        $settingsId = SessionManager::get('settings.id');
        $diff = SessionManager::get('settings.difficulty');
        $isDiff = fn($d) => $d == $diff ? ' ✅' : '';

        if (empty($settingsId)) return;

        $this->telegram->withOptions([
            'reply_markup' => [
                'inline_keyboard' => (new InlineKeyboard)
                    ->setRowMax(2)
                    ->addButton('Easy' . $isDiff('easy'), ['settings:diff' => 'easy'], InlineKeyboard::CALLBACK_DATA)
                    ->addButton('Medium' . $isDiff('medium'), ['settings:diff' => 'medium'], InlineKeyboard::CALLBACK_DATA)
                    ->addButton('Hard' . $isDiff('hard'), ['settings:diff' => 'hard'], InlineKeyboard::CALLBACK_DATA)
                    ->toArray(),
            ]
        ])->editMessage($settingsId, 'Choose your difficulty:');
    }

    /**
     * handle language value
     *
     * @param IncomingCallbackQuery $query
     * @return void
     * @throws Exception
     */
    #[CallbackQuery('settings:lang')]
    public function languageChanged(IncomingCallbackQuery $query): void
    {
        $settingsId = SessionManager::get('settings.id');
        $language = $query('settings:lang');

        if (empty($settingsId)) return;
        if (!in_array($language, ['en', 'es', 'pt'])) return;

        $session = SessionManager::get();
        $session['settings']['id'] = null;
        $session['settings']['language'] = $language;
        $session['feedback'] = $settingsId;

        unset($session['state']);
        SessionManager::set($session, SessionManager::get('state'));

        $this->telegram->editMessage($settingsId, 'Your preferred language has been saved!');
    }

    /**
     * handle difficulty value
     *
     * @param IncomingCallbackQuery $query
     * @return void
     * @throws Exception
     */
    #[CallbackQuery('settings:diff')]
    public function difficultyChanged(IncomingCallbackQuery $query): void
    {
        $settingsId = SessionManager::get('settings.id');
        $language = $query('settings:diff');

        if (empty($settingsId)) return;
        if (!in_array($language, ['easy', 'medium', 'hard'])) return;

        $session = SessionManager::get();
        $session['settings']['id'] = null;
        $session['settings']['difficulty'] = $language;
        $session['feedback'] = $settingsId;

        unset($session['state']);
        SessionManager::set($session, SessionManager::get('state'));

        $this->telegram->editMessage($settingsId, 'Your preferred difficulty has been saved!');
    }

}
