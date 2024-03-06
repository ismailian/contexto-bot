<?php

namespace TeleBot\App\Helpers;

class Pager
{

    /** @var int $max */
    protected static int $max = 9;

    /** @var int $limit */
    protected static int $limit;

    /**
     * set max
     *
     * @param int $max
     * @return Pager
     */
    public static function setMax(int $max = 9): Pager
    {
        self::$max = $max;

        return (new static);
    }

    /**
     * set limit
     *
     * @param int $limit
     * @return Pager
     */
    public static function setLimit(int $limit): Pager
    {
        self::$limit = $limit;

        return (new static);
    }

    /**
     * page backwards in a range
     *
     * @param int $start
     * @return object
     */
    public static function back(int $start): object
    {
        $pages = [];
        while (count($pages) < self::$max) {
            $val = $start--;
            if ($val == 0) break;

            $pages[] = $val;
        }

        $nextPage = max($pages) + 1;
        $backPage = min($pages) - 1;

        return (object)[
            'pages' => $pages,
            'next' => $nextPage >= self::$limit ? null : $nextPage,
            'back' => $backPage > 0 ? $backPage : null,
        ];
    }

    /**
     * page forwards in a range
     *
     * @param int $start
     * @return object
     */
    public static function next(int $start): object
    {
        $pages = [];
        while (count($pages) < self::$max) {
            $val = $start++;
            if ($val >= self::$limit) break;

            $pages[] = $val;
        }

        $nextPage = max($pages) + 1;
        $backPage = min($pages) - 1;

        sort($pages);
        return (object)[
            'pages' => $pages,
            'next' => $nextPage >= self::$limit ? null : $nextPage,
            'back' => $backPage > 0 ? $backPage : null,
        ];
    }

}