<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AdminController extends AbstractController
{
    #[Route('/admin/analytics', name: 'admin_analytics', methods: ['GET'])]
    public function analytics(): Response
    {
        return $this->render('admin/analytics.html.twig');
    }

    #[Route('/admin/cms', name: 'admin_cms', methods: ['GET'])]
    public function cms(): Response
    {
        return $this->render('admin/cms_pages.html.twig');
    }
}

