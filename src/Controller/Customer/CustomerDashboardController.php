<?php

declare(strict_types=1);

namespace App\Controller\Customer;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Customer Dashboard Controller
 */
#[Route('/customer', name: 'customer_')]
#[IsGranted('ROLE_CUSTOMER_USER')]
class CustomerDashboardController extends AbstractController
{
    /**
     * Customer dashboard main page
     */
    #[Route('/dashboard', name: 'dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        return $this->render('customer/dashboard.html.twig', [
            'user' => $this->getUser(),
            'customer' => $this->getUser()->getCustomer()
        ]);
    }

    /**
     * Customer shipments list
     */
    #[Route('/shipments', name: 'shipments', methods: ['GET'])]
    public function shipments(): Response
    {
        return $this->render('customer/shipments.html.twig', [
            'user' => $this->getUser(),
            'customer' => $this->getUser()->getCustomer()
        ]);
    }

    /**
     * Redirect to shipment success page
     */
    #[Route('/shipment/{id}/success', name: 'shipment_success', methods: ['GET'])]
    public function shipmentSuccess(int $id): Response
    {
        return $this->redirectToRoute('customer_shipment_wizard_success', ['id' => $id]);
    }
}