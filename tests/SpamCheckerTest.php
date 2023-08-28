<?php

namespace App\Tests;

use App\Entity\Comment;
use App\Service\SpamChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Component\HttpClient\Response\MockResponse;

class SpamCheckerTest extends TestCase
{
    public function testSpamScoreWithInvalidRequest(): void
    {
        $comment = new Comment();
        $comment->setCreatedAtValue();
        $context = [];
        // ! La classe MockHttpClient permet de simuler un serveur HTTP.On lui donne un tableau d'instance MockResponse contenant le corps et en-têtes de réponse attendus.
        $client = new MockHttpClient([new MockResponse('invalid', ['response_headers' => ['x-alismet-debug-help: Invalid key']])]);
        $checker = new SpamChecker($client, 'abcde');

        // ! Appel de la méthode getSpamScore et vérifiaction qu'une exception est levée via la méthode expectException de PHPUnit. 
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to check for spam : invalid (Invalid key).');
        $checker->getSpamScore($comment, $context);
    }

    // ! Les data providers de PHPUnit permettent de réutiliser la même logique pr plusieurs scénarios
    /**
     * @dataProvider provideComments
     */
    public function testSpamScore(int $expectedScore, ResponseInterface $response, Comment $comment, array $context)
    {
        $client = new MockHttpClient([$response]);
        $checker = new SpamChecker($client, 'abcde');

        $score = $checker->getSpamScore($comment, $context);
        $this->assertSame($expectedScore, $score);
    }

    public static function provideComments(): iterable
    {
        $comment = new Comment();
        $comment->setCreatedAtValue();
        $context = [];

        $response = new MockResponse('', ['response_headers' => ['x-akismet-pro-tip: discard']]);
        yield 'blatant_spam' => [2, $response, $comment, $context];

        $response = new MockResponse('true');
        yield 'spam' => [1, $response, $comment, $context];

        $response = new MockResponse('false');
        yield 'ham' => [0, $response, $comment, $context];
    }
}
