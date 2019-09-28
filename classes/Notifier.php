<?php

class Notifier
{
    const RESET = "\033[0m";
    const BOLD = "\033[1m";
    const ERASE_END = "\033[K";

    const COLOR_BLACK = "\033[30m";
    const COLOR_RED = "\033[31m";
    const COLOR_GREEN = "\033[32m";
    const COLOR_YELLOW = "\033[33m";
    const COLOR_BLUE = "\033[34m";
    const COLOR_MAGENTA = "\033[35m";
    const COLOR_CYAN = "\033[36m";
    const COLOR_WHITE = "\033[37m";

    const COLOR_BLACK_BG = "\033[40m";
    const COLOR_RED_BG = "\033[41m";
    const COLOR_GREEN_BG = "\033[42m";
    const COLOR_YELLOW_BG = "\033[43m";
    const COLOR_BLUE_BG = "\033[44m";
    const COLOR_MAGENTA_BG = "\033[45m";
    const COLOR_CYAN_BG = "\033[46m";
    const COLOR_WHITE_BG = "\033[47m";

    /**
     * Output a message to the console
     * @param string $message - a message text
     * @param boolean $isBold - set true to make the message text bold
     * @param string $color - a text color
     * @param string $bg - a background color
     *
     * @return void
     */
    public static function ShowNotify($message, $isBold = false, $color = false, $bg = false)
    {
        if ( !$message = trim($message) ) return;

        $color = $color ? 'COLOR_'.strtoupper(trim($color)) : false;
        $bg = $bg ? 'COLOR_'.strtoupper(trim($bg)).'_BG' : false;
        $isBold = boolval($isBold);

        if ( $color && defined("self::{$color}") ) echo constant("self::{$color}");
        if ( $bg && defined("self::{$bg}") ) echo constant("self::{$bg}");
        if ( $isBold ) echo self::BOLD;

        echo self::ERASE_END, $message, self::RESET, "\n";
    }

    /**
     * Output an error message to the console
     * @param string $message - a message text
     *
     * @return void
     */
    public static function ShowError($message)
    {
        self::ShowNotify($message, true, 'red');
    }

    /**
     * Output an warning message to the console
     * @param string $message - a message text
     *
     * @return void
     */
    public static function ShowWarning($message)
    {
        self::ShowNotify($message, false, 'yellow');
    }

    /**
     * Output an notice message to the console
     * @param string $message - a message text
     *
     * @return void
     */
    public static function ShowNotice($message)
    {
        self::ShowNotify($message, false, 'blue');
    }

    /**
     * Output an success message to the console
     * @param string $message - a message text
     *
     * @return void
     */
    public static function ShowSuccess($message)
    {
        self::ShowNotify($message, false, 'green');
    }
}
