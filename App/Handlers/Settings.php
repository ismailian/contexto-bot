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

class Settings extends BaseEvent
{

    /**
     * handle start command
     *
     * @return void
     * @throws Exception
     */
    #[Command('start')]
    public function welcome(): void
    {
        $messageId = $this->event['message']['message_id'];
        $feedbackId = SessionManager::get('feedback') ?? null;

        $this->telegram->deleteMessage($messageId);
        if ($feedbackId) {
            $this->telegram->deleteMessage($feedbackId);
        }

        $session = SessionManager::get();
        $session['settings'] = $session['settings'] ?? [
            'id' => null,
            'language' => 'en',
            'difficulty' => 'easy'
        ];

        $greeting = Misc::getGreeting($this->event['message']['from']['first_name']);
        $this->telegram->sendMessage($greeting);

        unset($session['state']);
        SessionManager::set($session);
    }

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
        $settingsId = SessionManager::get('settings.id') ?? null;

        $this->telegram->deleteMessage($messageId);
        if ($settingsId) $this->telegram->deleteMessage($settingsId);
        if ($feedbackId) $this->telegram->deleteMessage($feedbackId);

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
        $feedbackId = SessionManager::get('feedback');
        if ($feedbackId) {
            $this->telegram->deleteMessage($feedbackId);
        }

        $lang = SessionManager::get('settings.language');
        $isLang = fn($l) => $l == $lang ? '✅ ' : '';

        if (empty($settingsId)) return;

        $this->telegram->withOptions([
            'reply_markup' => [
                'inline_keyboard' => (new InlineKeyboard)
                    ->setRowMax(2)
                    ->addButton(($isLang('en') . 'English'), ['settings:lang' => 'en'], InlineKeyboard::CALLBACK_DATA)
                    ->addButton(($isLang('es') . 'Spanish'), ['settings:lang' => 'es'], InlineKeyboard::CALLBACK_DATA)
                    ->addButton(($isLang('pt') . 'Portuguese'), ['settings:lang' => 'pt'], InlineKeyboard::CALLBACK_DATA)
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
        $session = SessionManager::get();
        $settingsId = $session['settings']['id'];
        $language = $query('settings:lang');

        if (empty($settingsId)) return;
        if (!in_array($language, ['en', 'es', 'pt'])) return;

        $this->telegram->deleteMessage($settingsId);
        $this->telegram->sendMessage('Your preferred language has been saved!');

        $session['settings']['id'] = null;
        $session['settings']['language'] = $language;
        $session['feedback'] = $this->telegram->getLastMessageId();

        unset($session['state']);
        SessionManager::set($session, SessionManager::get('state'));
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
        $session = SessionManager::get();
        $settingsId = $session['settings']['id'];
        $language = $query('settings:diff');

        if (empty($settingsId)) return;
        if (!in_array($language, ['easy', 'medium', 'hard'])) return;

        $this->telegram->deleteMessage($settingsId);
        $this->telegram->sendMessage('Your difficulty level has been saved!');

        $session['settings']['id'] = null;
        $session['settings']['difficulty'] = $language;
        $session['feedback'] = $this->telegram->getLastMessageId();

        unset($session['state']);
        SessionManager::set($session, SessionManager::get('state'));
    }

}
