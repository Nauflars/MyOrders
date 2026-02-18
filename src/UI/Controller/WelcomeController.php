<?php

namespace App\UI\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class WelcomeController extends AbstractController
{
    #[Route('/', name: 'welcome', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('welcome/index.html.twig', [
            'title' => 'Welcome to MyOrders',
            'description' => 'DDD/CQRS Order Management System with Hexagonal Architecture',
            'features' => [
                'Domain-Driven Design (DDD)',
                'CQRS Pattern',
                'Hexagonal Architecture',
                'Event Sourcing',
                'Async Processing with RabbitMQ',
                'MySQL for Source of Truth',
                'MongoDB for Read Models',
            ],
            'environment' => $this->getParameter('kernel.environment'),
        ]);
    }

    #[Route('/health', name: 'health', methods: ['GET'])]
    public function health(): Response
    {
        return $this->json([
            'status' => 'UP',
            'service' => 'MyOrders',
            'timestamp' => (new \DateTime())->format(\DateTime::ATOM),
        ]);
    }

    #[Route('/health/detailed', name: 'health_detailed', methods: ['GET'])]
    public function healthDetailed(): Response
    {
        // TODO: Add actual health checks for MySQL, MongoDB, RabbitMQ
        return $this->json([
            'status' => 'UP',
            'service' => 'MyOrders',
            'version' => '1.0.0',
            'timestamp' => (new \DateTime())->format(\DateTime::ATOM),
            'environment' => $this->getParameter('kernel.environment'),
            'services' => [
                'mysql' => ['status' => 'pending_check'],
                'mongodb' => ['status' => 'pending_check'],
                'rabbitmq' => ['status' => 'pending_check'],
            ],
        ]);
    }
}
