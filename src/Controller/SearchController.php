<?php

namespace App\Controller;

use App\Service\GlobalSearch;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SearchController extends AbstractController
{
    #[Route('/buscar', name: 'search', methods: ['GET'])]
    public function search(Request $request, GlobalSearch $search): Response
    {
        $q = trim((string) $request->query->get('q', ''));

        return $this->render('search/index.html.twig', [
            'q' => $q,
            'groups' => $search->search($q, 20),
        ]);
    }
}
