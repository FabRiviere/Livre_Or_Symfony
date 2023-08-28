<?php

namespace App\Tests\Controller;

use App\Repository\CommentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ConferenceControllerTest extends WebTestCase
{
    public function testIndex()
    {
        $client = static::createClient();
        $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h2', 'Give your feedback');
    }

    public function testConferencePage()
    {
        $client = static::createClient();
        // ! On retourne 1 instance de crawler qui aide à trouver des éléments sur la page.
        $crawler = $client->request('GET', '/');
        // ! On vérifie qu'on a bien 2 conférences listées sur la page.
        $this->assertCount(2, $crawler->filter('h4'));

        // ! On clique sur le view -> le premier qu'il trouve.
        $client->clickLink('View');

        // ! On vérifie le titre de la page et que la réponse est le h2 pour être sur d'être sur la bonne page.
        $this->assertPageTitleContains('Amsterdam');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h2', 'Amsterdam 2019');

        // ! On vérife qu'il y a 1 commentaire sur la page.
        $this->assertSelectorExists('div:contains("There are 1 comments")');
    }

    public function testCommentSubmission()
    {
        $client = static::createClient();
        $client->request('GET', '/conference/amsterdam-2019');
        $client->submitForm('Submit', [
            'comment_form[author]' => 'Fabien',
            'comment_form[text]' => 'Some feedback from an automated functional test',
            'comment_form[email]' => $email = 'me@automat.ed',
            'comment_form[photo]' => dirname(__DIR__, 2). '/public/images/under-construction.gif',
        ]);
        $this->assertResponseRedirects();

        //! simulate comment validation
        $comment = self::getContainer()->get(CommentRepository::class)->findOneByEmail($email);
        $comment->setState('published');
        self::getContainer()->get(EntityManagerInterface::class)->flush();
        
        $client->followRedirect();
        $this->assertSelectorExists('div:contains("There are 2 comments")');
    }
}