<?php

namespace TeleBot\App\Helpers;

use DateTime;
use Exception;

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
     * get distance rate
     *
     * @param $distance
     * @return object
     */
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
        if ($result < 1) $result = 1;

        return (object)[
            'value' => $result,
            'type' => ($result < 4) ? 'H' : (($result < 8) ? 'M' : 'L'),
        ];
    }

    /**
     * get greeting template
     *
     * @param string $firstName user first name
     * @return string returns a compiled greeting template
     */
    public static function getGreeting(string $firstName): string
    {
        return "Hello, $firstName!\nwelcome to Guess The Word\n\nStart playing the game by clicking the menu button and clicking /play option.\n\nIf you need a hint, click /hint.\n\nIf you can't figure out the word,\nyou can click /giveup option and get the correct answer.\n\nTo change your language/difficulty, click /settings";
    }

    /**
     * get game template
     *
     * @param array $session
     * @param bool $isCorrectWord
     * @return string
     */
    public static function getTemplate(array $session, bool $isCorrectWord = false): string
    {
        $template = "Today's game: #{gameId}\n\n";
        $template .= "Guesses: {guessCount}\n";
        $template .= "Hints: {hintCount}\n";
        $template .= "Distance: {distance}\n\n";
        $template .= ($isCorrectWord ? 'Correct word' : 'Last word') . ": {lastWord}\n";
        $template .= "{progress}";

        return self::build($template, $session);
    }

    /**
     * build progress template
     *
     * @param string $template
     * @param array $session
     * @return string
     */
    protected static function build(string $template, array $session): string
    {
        $bar = [];
        $types = [
            'N' => '⚪', 'H' => '🔴',
            'M' => '🟡', 'L' => '🟢',
        ];

        $template = str_replace('{gameId}', $session['id'], $template);
        $template = str_replace('{guessCount}', $session['guesses'], $template);
        $template = str_replace('{hintCount}', $session['hints'], $template);
        $template = str_replace('{distance}', $session['distance'], $template);
        $template = str_replace('{lastWord}', $session['last_word'], $template);

        /** progress */
        $progressType = $session['progress']['type'];
        $progressValue = $session['progress']['value'];
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

    /**
     * get tip distance based on difficulty level
     *
     * @param string $difficulty
     * @param array $history
     * @return int
     */
    public static function getTipDistance(string $difficulty, array $history = []): int
    {
        return match ($difficulty) {
            'easy' => self::getHalfTipDistance($history),
            'medium' => self::getNextTipDistance($history),
            'hard' => self::getRandomTipDistance($history),
        };
    }

    /**
     * get half tip distance (Easy)
     *
     * @param array $guessHistory
     * @return int
     */
    protected static function getHalfTipDistance(array $guessHistory = []): int
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
     * get random tip distance (Hard)
     *
     * @param array $guessHistory
     * @return int
     */
    protected static function getRandomTipDistance(array $guessHistory = []): int
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

    /**
     * get next tip distance (Medium)
     *
     * @param array $guessHistory
     * @return int
     */
    protected static function getNextTipDistance(array $guessHistory = []): int
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

}
