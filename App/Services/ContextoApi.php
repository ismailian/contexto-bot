<?php

namespace TeleBot\App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class ContextoApi
{

    protected static string $baseUrl = 'https://api.contexto.me';
    protected static Client $client;

    /**
     * get http client
     *
     * @return Client
     */
    protected static function getClient(): Client
    {
        if (empty(self::$client)) {
            return self::$client = new Client([
                'verify' => false,
                'base_uri' => self::$baseUrl
            ]);
        }

        return self::$client;
    }

    /**
     * check guessed word
     *
     * @param int $gameId
     * @param string $word
     * @param string $language
     * @return object|null
     */
    public static function guess(int $gameId, string $word, string $language = 'en'): ?object
    {
        try {
            $response = self::getClient()->get("/machado/$language/game/$gameId/$word");
            return json_decode($response->getBody());
        } catch (GuzzleException $e) {
          if (str_contains($e->getMessage(), "I don't know this word")) {
            // throw WordUnknownException
          }
        }

        return null;
    }

    /**
     * get hint
     *
     * @param int $gameId
     * @param int $distance
     * @param string $language
     * @return object|null
     */
    public static function getHint(int $gameId, int $distance, string $language = 'en'): ?object
    {
        try {
            $response = self::getClient()->get("/machado/$language/tip/$gameId/$distance");
            return json_decode($response->getBody());
        } catch (GuzzleException $e) {}
        return null;
    }

    /**
     * get the closest words
     *
     * @param int $gameId
     * @param string $language
     * @return object|null
     */
    public static function getClosestWords(int $gameId, string $language = 'en'): ?array
    {
        try {
            $response = self::getClient()->get("/machado/$language/top/$gameId");
            return json_decode($response->getBody())['words'];
        } catch (GuzzleException $e) {}
        return null;
    }

    /**
     * get the correct answer
     *
     * @param int $gameId
     * @param string $language
     * @return object|null
     */
    public static function giveUp(int $gameId, string $language = 'en'): ?object
    {
        try {
            $response = self::getClient()->get("/machado/$language/giveup/$gameId");
            return json_decode($response->getBody());
        } catch (GuzzleException $e) {}
        return null;
    }
}
