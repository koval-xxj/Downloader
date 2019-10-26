<?php

declare(ticks = 1);
pcntl_signal(SIGINT, function($signo) {
    fwrite(STDOUT, "\n\033[?25h");
    fwrite(STDERR, "\n\033[?25h");
    exit;
});

class ProgressBar extends Notifier
{
    const MOVE_START = "\033[1G";
    const HIDE_CURSOR = "\033[?25l";
    const SHOW_CURSOR = "\033[?25h";
    const ERASE_LINE = "\033[2K";

    /**
     * Available screen width
     * @var integer
     */
    private $width;

    /**
     * Ouput stream. Usually STDOUT or STDERR
     * @var resource
     */
    private $stream;

    /**
     * Output string format
     * @var string
     */
    private $format;

    /**
     * Time the progress bar was initialised in seconds (with millisecond precision)
     * @var integer
     */
    private $startTime;

    /**
     * Time since the last draw
     * @var integer
     */
    private $timeSinceLastCall;

    /**
     * Pre-defined tokens in the format
     * @var array
     */
    private $ouputFind = array(':current', ':total', ':elapsed', ':percent', ':eta', ':speed');

    /**
     * Do not run drawBar more often than this (bypassed by interupt())
     * @var float
     */
    public $throttle = 0.016; // 16 ms

    /**
     * The symbol to denote completed parts of the bar
     * @var string
     */
    public $symbolComplete = "=";

    /**
     * The symbol to denote incomplete parts of the bar
     * @var string
     */
    public $symbolIncomplete = " ";

    /**
     * Current tick number
     * @var integer
     */
    public $current = 0;

    /**
     * Maximum number of ticks
     * @var integer
     */
    public $total = 1;

    /**
     * Seconds elapsed
     * @var integer
     */
    public $elapsed = 0;

    /**
     * Current percentage complete
     * @var integer
     */
    public $percent = 0;

    /**
     * Estimated time until completion
     * @var integer
     */
    public $eta = 0;

    /**
     * Current speed
     * @var integer
     */
    public $speed = 0;

    /**
     * Initialization
     *
     * @param integer $total
     * @param string $format
     * @param resource $stream
     *
     * @return void
     */
    public function __construct($total = 1, $format = "Progress: [:bar] - :current/:total - :percent% - Elapsed::elapseds - ETA::etas - Speed::speed/s", $stream = STDERR)
    {
        // Get the terminal width
        $this->width = exec("tput cols");
        if (!is_numeric($this->width)) {
            // Default to 80 columns, mainly for windows users with no tput
            $this->width = 80;
        }

        $this->total = $total;
        $this->format = $format;
        $this->stream = $stream;

        // Initialise the display
        fwrite($this->stream, self::HIDE_CURSOR);
        fwrite($this->stream, self::MOVE_START);

        // Set the start time
        $this->startTime = microtime(true);
        $this->timeSinceLastCall = microtime(true);

        $this->drawBar();
    }

    /**
     * Add $amount of ticks. Usually 1, but maybe different amounts if calling
     * this on a timer or other unstable method, like a file download.
     *
     * @param integer $amount
     * @return void
     */
    public function tick($amount = 1)
    {
        $this->update($this->current + $amount);
    }

    /**
     * Set $amount of ticks
     *
     * @param integer $amount
     * @return void
     */
    public function update($amount)
    {
        $this->current = $amount;
        $this->elapsed = microtime(true) - $this->startTime;
        $this->percent = $this->current / $this->total * 100;
        $this->speed = $this->current / $this->elapsed;
        $this->eta = ($this->current) ? ($this->elapsed / $this->current * $this->total - $this->elapsed) : false;
        $drawElapse = microtime(true) - $this->timeSinceLastCall;
        $this->drawBar();
    }

    /**
     * Add a message on a newline before the progress bar
     *
     * @param string $message
     */
    public function interupt($message)
    {
        fwrite($this->stream, self::MOVE_START);
        fwrite($this->stream, self::ERASE_LINE);

        fwrite($this->stream, self::COLOR_BLUE);
        fwrite($this->stream, self::ERASE_END);
        fwrite($this->stream, $message);
        fwrite($this->stream, self::RESET);
        fwrite($this->stream, "\n");
        $this->drawBar();
    }

    /**
     * Does the actual
     *
     * @return void
     */
    private function drawBar()
    {
        $this->timeSinceLastCall = microtime(true);
        fwrite($this->stream, self::MOVE_START);

        $replace = array(
            $this->current,
            $this->total,
            $this->roundAndPadd($this->elapsed),
            $this->roundAndPadd($this->percent),
            $this->roundAndPadd($this->eta),
            $this->roundAndPadd($this->speed),
        );

        $output = str_replace($this->ouputFind, $replace, $this->format);

        if (strpos($output, ':bar') !== false) {
            $availableSpace = $this->width - strlen($output) + 4;
            $done = $availableSpace * ($this->percent / 100);
            $left = $availableSpace - $done;
            $output = str_replace(':bar', str_repeat($this->symbolComplete, $done) . str_repeat($this->symbolIncomplete, $left), $output);
        }

        fwrite($this->stream, $output);
    }

    /**
     * Adds 0 and space padding onto floats to ensure the format is fixed length nnn.nn
     *
     * @param string $input
     * @return string
     */
    private function roundAndPadd($input)
    {
        $parts = explode(".", round($input, 2));
        $output = $parts[0];
        if (isset($parts[1])) {
            $output .= "." . str_pad($parts[1], 2, 0);
        } else {
            $output .= ".00";
        }

        return str_pad($output, 6, " ", STR_PAD_LEFT);
    }

    /**
     * Cleanup
     *
     * @return void
     */
    public function end()
    {
        fwrite($this->stream, "\n" . self::SHOW_CURSOR);
    }

    public function __destruct()
    {
        $this->end();
    }

}
