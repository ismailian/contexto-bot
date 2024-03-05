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
            $pages[] = $start--;
        }

        return (object)[
            'pages' => $pages,
            'next' => $pages[count($pages) - 1],
            'back' => $pages[0] - 1
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
            $pages[] = $start++;
        }

        sort($pages);
        return (object)[
            'pages' => $pages,
            'next' => $pages[0] - 1,
            'back' => $pages[count($pages) - 1],
        ];
    }

}