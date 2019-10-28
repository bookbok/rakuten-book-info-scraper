<?php
/**
 * bookbok/rakuten-book-info-scraper
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
namespace BookBok\BookInfoScraper\Rakuten;

use BookBok\BookInfoScraper\AbstractIsbnScraper;
use BookBok\BookInfoScraper\Exception\DataProviderException;
use BookBok\BookInfoScraper\Information\BookInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;

/**
 *
 */
class RakutenScraper extends AbstractIsbnScraper{

    private const API_URI = "https://app.rakuten.co.jp/services/api/BooksBook/Search/20170404";

    /**
     * @var string
     */
    private $applicationId;

    /**
     * @var ClientInterface
     */
    private $client;

    /**
     * @var RequestFactoryInterface
     */
    private $requestFactory;

    /**
     * Constructor.
     *
     * @param string                  $applicationId
     * @param ClientInterface         $client
     * @param RequestFactoryInterface $requestFactory
     */
    public function __construct(
        string $applicationId,
        ClientInterface $client,
        RequestFactoryInterface $requestFactory
    ){
        $this->setApplicationId($applicationId);

        $this->client = $client;
        $this->requestFactory = $requestFactory;
    }

    /**
     * Get application id.
     *
     * @return string
     */
    public function getApplicationId(): string{
        return $this->applicationId;
    }

    /**
     * Set application id.
     *
     * @param string $applicationId
     *
     * @return void
     */
    public function setApplicationId(string $applicationId): void{
        if("" === $applicationId){
            throw new \InvalidArgumentException();
        }

        $this->applicationId    = $applicationId;
    }

    /**
     * Get http client.
     *
     * @return ClientInterface
     */
    public function getHttpClient(): ClientInterface{
        return $this->client;
    }

    /**
     * Set http client.
     *
     * @param ClientInterface $client
     *
     * @return void
     */
    public function setHttpClient(ClientInterface $client): void{
        $this->client   = $client;
    }

    /**
     * Get request factory.
     *
     * @return RequestFactoryInterface
     */
    public function getRequestFactory(): RequestFactoryInterface{
        return $this->requestFactory;
    }

    /**
     * Set request factory.
     *
     * @param RequestFactoryInterface $requestFactory
     *
     * @return void
     */
    public function setRequestFactory(RequestFactoryInterface $requestFactory): void{
        $this->requestFactory   = $requestFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function scrape(string $id): ?BookInterface{
        try{
            $response   = $this->getHttpClient()->sendRequest(
                $this->createRequest($id)
            );
        }catch(ClientExceptionInterface $e){
            throw new DataProviderException(
                $e->getMessage(),
                $e->getCode(),
                $e
            );
        }

        $json   = json_decode($response->getBody()->getContents(), true);

        if(JSON_ERROR_NONE !== json_last_error()){
            throw new DataProviderException(json_last_error_msg());
        }

        if(401 === $response->getStatusCode()){
            throw new DataProviderException(
                $json["error_description"] ?? "application id is invalid."
            );
        }

        if(200 !== $response->getStatusCode()){
            return null;
        }

        if(1 !== $json["count"]){
            return null;
        }

        return $this->generateBook($json["Items"][0]["Item"]);
    }

    /**
     * Create request instance.
     *
     * @param string $id
     *
     * @return RequestInterface
     */
    protected function createRequest(string $id): RequestInterface{
        $data   = [
            "format"        => "json",
            "isbn"          => $id,
            "applicationId" => $this->getApplicationId(),
        ];

        $query  = implode(
            "&",
            array_map(
                function($key, $val){
                    return $key . "=" . rawurlencode($val);
                },
                array_keys($data),
                array_values($data)
            )
        );

        return $this->getRequestFactory()
            ->createRequest(
                "GET",
                static::API_URI . "?" . $query
            )
        ;
    }

    /**
     * Generate book instance.
     *
     * @param mixed[] $data
     *
     * @return BookInterface|null
     */
    protected function generateBook(array $data): ?BookInterface{
        $book       = new RakutenBook($data);

        try{
            $publishedAt    = $this->generatePublishedAt($book->get("salesDate", ""));
        }catch(\Exception $e){
            throw new DataProviderException($e->getMessage(), $e->getCode(), $e);
        }

        $book
            ->setSubTitle("" !== $book->get("subTitle") ?: null)
            ->setDescription($book->get("itemCaption"))
            ->setCoverUri($book->get("largeImageUrl"))
            ->setAuthors(array_map(
                function($author){return new RakutenAuthor(trim($author));},
                explode("/", $book->get("author"))
            ))
            ->setPublisher($book->get("publisherName"))
            ->setPublishedAt($publishedAt)
            ->setPrice($book->get("itemPrice"))
            ->setPriceCode("JPY")
        ;

        return $book;
    }

    /**
     * Generate published ad.
     *
     * @param string $date
     *
     * @return \DateTime|null
     *
     * @throws \Exception
     */
    protected function generatePublishedAt(string $date): ?\DateTime{
        if(1 !== preg_match("/\A([0-9]+)年([0-9]+)月([0-9]+)日\z/u", $date, $m)){
            return null;
        }

        return new \DateTime("{$m[1]}-{$m[2]}-{$m[3]}");
    }
}
