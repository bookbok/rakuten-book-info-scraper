<?php
/**
 * kentoka/rakuten-book-info-scraper
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.
 * Redistributions of files must retain the above copyright notice.
 *
 * @author      Kento Oka <kento-oka@kentoka.com>
 * @copyright   (c) Kento Oka
 * @license     MIT
 * @since       1.0.0
 */
namespace Kentoka\BookInfoScraper\Rakuten;

use Kentoka\BookInfoScraper\Information\Book;

/**
 *
 */
class RakutenBook extends Book{

    /**
     * @var mixed[]
     */
    private $data;

    /**
     * Constructor.
     *
     * @param   mixed[] $data
     */
    public function __construct(array $data){
        $this->data = $data;

        parent::__construct($this->get("isbn"), $this->get("title"));
    }

    /**
     * Get raw data.
     *
     * @param string $accessKey
     * @param mixed  $default
     *
     * @return mixed|null
     */
    public function get(string $accessKey, $default = null){
        return $this->data[$accessKey] ?? $default;
    }
}
