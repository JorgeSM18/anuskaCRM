<?php

namespace App\Controller;

use App\Service\DashboardProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    #[Route('/', name: 'dashboard')]
    public function index(DashboardProvider $dashboard): Response
    {
        return $this->render('dashboard/index.html.twig', [
            'data' => $dashboard->build(),
        ]);
    }
}
