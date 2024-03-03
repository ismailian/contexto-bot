<?php

namespace TeleBot\App\Helpers;

use DateTime;
use Exception;
use TeleBot\System\SessionManager;

class Misc
{

    const PT_START_DATE = '2022-02-23';
    const EN_START_DATE = '2022-09-18';
    const ES_START_DATE = '2023-05-26';

    const TOTAL = 40000;
    const GREEN_THRESHOLD = 300;
    const YELLOW_THRESHOLD = 1500;

    /**
     * get today's game id
     *
     * @param string $language
     * @return int
     */
    public static function getTodaysGameId(string $language = 'en'): int
    {
        try {
            $dt = new DateTime();
            $diff = $dt->diff(new DateTime(match ($language) {
                'en' => self::EN_START_DATE,
                'es' => self::ES_START_DATE,
                'pt' => self::PT_START_DATE,
            }));

            return $diff->days;
        } catch (Exception) {}
        return 1;
    }

    /**
     * get next tip distance
     *
     * @param array $guessHistory
     * @return int
     */
    public static function getNextTipDistance(array $guessHistory = []): int
    {
        $tipDistance = self::GREEN_THRESHOLD - 1;
        $lowestDistance = $tipDistance;

        if (!empty($guessHistory)) {
            $distances = array_map(fn($d) => $d[1], $guessHistory);
            $lowestDistance = min([...$distances, $lowestDistance]);
            if ($lowestDistance > 1) $tipDistance = $lowestDistance - 1;
            else {
                $tipDistance = 2;
                while (in_array($tipDistance, $distances)) {
                    $tipDistance += 1;
                }
            }
        }

        return $tipDistance;
    }

    /**
     * get half tip distance
     *
     * @param array $guessHistory
     * @return int
     */
    public static function getHalfTipDistance(array $guessHistory = []): int
    {
        $tipDistance = self::GREEN_THRESHOLD - 1;
        $lowestDistance = $tipDistance * 2;

        if (!empty($guessHistory)) {
            $distances = array_map(fn($d) => $d[1], $guessHistory);
            $lowestDistance = min([...$distances, $lowestDistance]);
            if ($lowestDistance > 1) $tipDistance = floor($lowestDistance / 2);
            else {
                $tipDistance = 2;
                while (in_array($tipDistance, $distances)) {
                    $tipDistance += 1;
                }
            }
        }

        return $tipDistance;
    }

    /**
     * get random tip distance
     *
     * @param array $guessHistory
     * @return int
     */
    public static function getRandomTipDistance(array $guessHistory = []): int
    {
        $maxDistance = self::GREEN_THRESHOLD - 1;
        $tipDistance = floor(rand(1, $maxDistance - 1)) + 1;

        if (!empty($guessHistory)) {
            $distances = array_map(fn($d) => $d[1], $guessHistory);
            while (in_array($distances, $distances)) {
                $tipDistance = floor(rand(1, $maxDistance - 1)) + 1;
            }
        }

        return $tipDistance;
    }

    public static function getRate($distance): object
    {
        $total = 40000;
        $base = 0.5;
        $calc = fn($val) => $base * exp(-$base * $val);

        $startX = 0;
        $endX = 10;
        $startY = $calc($startX);
        $endY = $calc($endX);

        $x = ($distance / $total) * ($endX - $startX);
        $result = (int)((($calc($x) - $endY) / ($startY - $endY)) * 10);
        if ($result < 1) {
            $result = 1;
        }

        return (object)[
            'value' => $result,
            'type' => ($result < 4) ? 'H' : (($result < 8) ? 'M' : 'L'),
        ];
    }

    /**
     * get game template
     *
     * @param bool $isCorrectWord
     * @return string
     */
    public static function getTemplate(bool $isCorrectWord = false): string
    {
        $template = "Today's game: #{gameId}\n\n";
        $template .= "Guesses: {guessCount}\n";
        $template .= "Hints: {hintCount}\n";
        $template .= "Distance: {distance}\n\n";
        $template .= ($isCorrectWord ? 'Correct word' : 'Last word') . ": {lastWord}\n";
        $template .= "{progress}";

        return self::build($template);
    }

    /**
     * build progress template
     *
     * @param string $template
     * @return string
     */
    protected static function build(string $template): string
    {
        $bar = [];
        $types = [
            'N' => '⚪', 'H' => '🔴',
            'M' => '🟡', 'L' => '🟢',
        ];

        $template = str_replace('{gameId}', SessionManager::get('game.id'), $template);
        $template = str_replace('{guessCount}', SessionManager::get('game.guesses'), $template);
        $template = str_replace('{hintCount}', SessionManager::get('game.hints'), $template);
        $template = str_replace('{distance}', SessionManager::get('game.distance'), $template);
        $template = str_replace('{lastWord}', SessionManager::get('game.last_word'), $template);

        /** progress */
        $progressType = SessionManager::get('game.progress.type');
        $progressValue = SessionManager::get('game.progress.value');
        if ($progressValue > 0) {
            for ($i = 0; $i < $progressValue; $i++) {
                $bar[] = $types[$progressType];
            }
        }

        $remaining = (10 - $progressValue);
        if ($remaining > 0) {
            for ($i = 0; $i < $remaining; $i++) {
                $bar[] = $types['N'];
            }
        }

        return str_replace('{progress}', join('', $bar), $template);
    }

}
