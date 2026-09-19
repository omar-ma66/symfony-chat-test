<?php

namespace App\Controller;

use App\Entity\Message;
use App\Repository\MessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Routing\Attribute\Route;

class ChatController extends AbstractController
{
    #[Route('/chat', name: 'app_chat')]
    public function index(MessageRepository $messageRepository): Response
    {
        return $this->render('chat/index.html.twig', [
            'messages' => $messageRepository->findBy([], ['createAt' => 'ASC'], 50),
        ]);
    }

    #[Route('/chat/send', name: 'app_chat_send', methods: ['POST'])]
    public function send(Request $request, EntityManagerInterface $em, HubInterface $hub): Response
    {
        $content = $request->request->get('content');
        if (!$content) {
            return $this->json(['status' => 'error'], 400);
        }

        $message = new Message();
        $message->setContent($content);
        $message->setSender($this->getUser()); // Utilisateur connecte
        $message->setCreateAt(new \DateTimeImmutable());

        $em->persist($message);
        $em->flush();

        // 1. Création de la mise à jour Mercure
        $update = new Update(
            'http://example.com/chat', // Topic (sujet)
            json_encode([
                'sender' => $this->getUser()->getUserIdentifier(),
                'content' => $message->getContent(),
                'createdAt' => $message->getCreateAt()->format('H:i')
            ])
        );

        // 2. Publication sur le Hub Mercure
        $hub->publish($update);

        return $this->json(['status' => 'success']);
    }
}