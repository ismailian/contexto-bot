<?php

namespace TeleBot\App\Handlers;

use TeleBot\System\BaseEvent;
use TeleBot\System\Events\Command;
use TeleBot\System\SessionManager;
use TeleBot\System\Events\CallbackQuery;
use TeleBot\System\Types\InlineKeyboard;
use TeleBot\System\Events\IncomingCallbackQuery;

class Settings extends BaseEvent
{

  /**
   * handle settings command
   *
   * @return void
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
  }

  /**
   * handle language settings
   *
   * @param IncomingCallbackQuery $query
   * @return void
   */
  #[CallbackQuery('settings', 'language')]
  public function language(IncomingCallbackQuery $query): void
  {
  }

  /**
   * handle difficulty settings
   * 
   * @param IncomingCallbackQuery $query
   * @return void
   */
  #[CallbackQuery('settings', 'difficulty')]
  public function difficulty(IncomingCallbackQuery $query): void
  {
  }

  /**
   * handle language value
   * 
   * @param IncomingCallbackQuery $query
   * @return void
   */
  #[CallbackQuery('settings:lang')]
  public function languageChanged(IncomingCallbackQuery $query): void
  {
  }

  /**
   * handle difficulty value
   * 
   * @param IncomingCallbackQuery $query
   * @return void
   */
  #[CallbackQuery('settings:diff')]
  public function difficultyChanged(IncomingCallbackQuery $query): void
  {
  }
}
