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
            ->addButton('Language', 'language', InlineKeyboard::CALLBACK_DATA)
            ->addButton('Difficulty', 'difficulty', InlineKeyboard::CALLBACK_DATA)
            ->toArray()
        ]
      ])->sendMessage('Choose settings to customize:');

    }

}
