<?php

namespace TeleBot\App\Handlers;

use Exception;
use TeleBot\System\BaseEvent;
use TeleBot\System\Events\Command;
use TeleBot\System\SessionManager;

class History extends BaseEvent
{

    /**
     * handle history command
     *
     * @return void
     * @throws Exception
     */
    #[Command('history')]
    public function history(): void
    {
        $messageId = $this->event['message']['message_id'];
        $session = SessionManager::get();
        $feedback = $session['feedback'] ?? null;
        $playedGames = $session['history'] ?? [];
        $total = count($playedGames);

        $this->telegram->deleteMessage($messageId);
        if ($feedback) {
            $this->telegram->deleteMessage($feedback);
        }

        $template = "You played {$total} game(s).\n\n";
        foreach ($playedGames as $playedGame) {
            $template .= ($playedGame['status'] == 'won' ? '✅' : '❌') . " #{$playedGame['id']}\n";
        }

        $this->telegram->sendMessage($template);
        $session['feedback'] = $this->telegram->getLastMessageId();

        unset($session['state']);
        SessionManager::set($session, SessionManager::get('state'));
    }

}