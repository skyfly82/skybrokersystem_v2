<?php

declare(strict_types=1);

namespace App\Controller\Customer;

use App\Service\Shipment\ShipmentWizardService;
use App\Service\Shipment\PricingCalculatorService;
use App\Service\AddressBook\AddressBookService;
use App\Service\InPost\InPostService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * 4-Step Shipment Creation Wizard Controller
 * Provides guided shipment creation with session-based persistence
 */
#[Route('/customer/shipments/wizard', name: 'customer_shipment_wizard_')]
#[IsGranted('ROLE_CUSTOMER_USER')]
class ShipmentWizardController extends AbstractController
{
    public function __construct(
        private readonly ShipmentWizardService $wizardService,
        private readonly PricingCalculatorService $pricingCalculator,
        private readonly AddressBookService $addressBookService,
        private readonly InPostService $inPostService
    ) {
    }

    /**
     * Start new shipment wizard
     */
    #[Route('', name: 'start', methods: ['GET'])]
    public function startWizard(SessionInterface $session): Response
    {
        // Clear any existing wizard data
        $session->remove('shipment_wizard');

        return $this->render('customer/shipment_wizard/start.html.twig', [
            'step' => 1,
            'total_steps' => 4,
        ]);
    }

    /**
     * Step 1: Package Type Selection
     */
    #[Route('/step/1', name: 'step1', methods: ['GET', 'POST'])]
    public function step1(Request $request, SessionInterface $session): Response
    {
        if ($request->isMethod('POST')) {
            $data = json_decode($request->getContent(), true);

            // Validate package type data
            $validationResult = $this->wizardService->validateStep1($data);
            if (!$validationResult['valid']) {
                return $this->json([
                    'success' => false,
                    'errors' => $validationResult['errors']
                ]);
            }

            // Save step 1 data to session
            $wizardData = $session->get('shipment_wizard', []);
            $wizardData['step1'] = $data;
            $session->set('shipment_wizard', $wizardData);

            return $this->json(['success' => true, 'next_step' => 2]);
        }

        $customerId = $this->getUser()->getCustomer()->getId();
        $packageTypes = $this->wizardService->getAvailablePackageTypes();
        $recentShipments = $this->wizardService->getRecentShipmentTemplates($customerId);

        return $this->render('customer/shipment_wizard/step1.html.twig', [
            'step' => 1,
            'total_steps' => 4,
            'package_types' => $packageTypes,
            'recent_shipments' => $recentShipments,
            'wizard_data' => $session->get('shipment_wizard', [])
        ]);
    }

    /**
     * Step 2: Address Management
     */
    #[Route('/step/2', name: 'step2', methods: ['GET', 'POST'])]
    public function step2(Request $request, SessionInterface $session): Response
    {
        $wizardData = $session->get('shipment_wizard', []);

        if (empty($wizardData['step1'])) {
            return $this->redirectToRoute('customer_shipment_wizard_step1');
        }

        if ($request->isMethod('POST')) {
            $data = json_decode($request->getContent(), true);

            // Validate address data
            $validationResult = $this->wizardService->validateStep2($data);
            if (!$validationResult['valid']) {
                return $this->json([
                    'success' => false,
                    'errors' => $validationResult['errors']
                ]);
            }

            // Save step 2 data to session
            $wizardData['step2'] = $data;
            $session->set('shipment_wizard', $wizardData);

            return $this->json(['success' => true, 'next_step' => 3]);
        }

        $customerId = $this->getUser()->getCustomer()->getId();
        $addresses = $this->addressBookService->getCustomerAddresses($customerId);

        return $this->render('customer/shipment_wizard/step2.html.twig', [
            'step' => 2,
            'total_steps' => 4,
            'saved_addresses' => $addresses,
            'wizard_data' => $wizardData
        ]);
    }

    /**
     * Step 3: Additional Services
     */
    #[Route('/step/3', name: 'step3', methods: ['GET', 'POST'])]
    public function step3(Request $request, SessionInterface $session): Response
    {
        $wizardData = $session->get('shipment_wizard', []);

        if (empty($wizardData['step1']) || empty($wizardData['step2'])) {
            return $this->redirectToRoute('customer_shipment_wizard_step1');
        }

        if ($request->isMethod('POST')) {
            $data = json_decode($request->getContent(), true);

            // Validate services data
            $validationResult = $this->wizardService->validateStep3($data);
            if (!$validationResult['valid']) {
                return $this->json([
                    'success' => false,
                    'errors' => $validationResult['errors']
                ]);
            }

            // Save step 3 data to session
            $wizardData['step3'] = $data;
            $session->set('shipment_wizard', $wizardData);

            return $this->json(['success' => true, 'next_step' => 4]);
        }

        $availableServices = $this->wizardService->getAvailableServices($wizardData);

        return $this->render('customer/shipment_wizard/step3.html.twig', [
            'step' => 3,
            'total_steps' => 4,
            'available_services' => $availableServices,
            'wizard_data' => $wizardData
        ]);
    }

    /**
     * Step 4: Courier Comparison and Payment
     */
    #[Route('/step/4', name: 'step4', methods: ['GET', 'POST'])]
    public function step4(Request $request, SessionInterface $session): Response
    {
        $wizardData = $session->get('shipment_wizard', []);

        if (empty($wizardData['step1']) || empty($wizardData['step2']) || empty($wizardData['step3'])) {
            return $this->redirectToRoute('customer_shipment_wizard_step1');
        }

        if ($request->isMethod('POST')) {
            $data = json_decode($request->getContent(), true);
            $customerId = $this->getUser()->getCustomer()->getId();

            try {
                // Complete shipment creation
                $shipment = $this->wizardService->createShipment($customerId, $wizardData, $data);

                // Clear wizard data
                $session->remove('shipment_wizard');

                return $this->json([
                    'success' => true,
                    'shipment_id' => $shipment->getId(),
                    'tracking_number' => $shipment->getTrackingNumber(),
                    'redirect_url' => $this->generateUrl('customer_shipment_success', ['id' => $shipment->getId()])
                ]);
            } catch (\Exception $e) {
                return $this->json([
                    'success' => false,
                    'message' => 'Failed to create shipment: ' . $e->getMessage()
                ]);
            }
        }

        // Get courier comparison data
        $courierComparison = $this->wizardService->getAvailableCouriers($wizardData);
        $customerId = $this->getUser()->getCustomer()->getId();
        $customer = $this->getUser()->getCustomer();

        return $this->render('customer/shipment_wizard/step4.html.twig', [
            'step' => 4,
            'total_steps' => 4,
            'courier_options' => $courierComparison,
            'customer_balance' => $customer->getBalance(),
            'wizard_data' => $wizardData
        ]);
    }

    /**
     * Get real-time pricing
     */
    #[Route('/pricing', name: 'get_pricing', methods: ['POST'])]
    public function getRealTimePricing(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        try {
            $pricing = $this->pricingCalculator->calculateShipmentPricing($data);

            return $this->json([
                'success' => true,
                'pricing' => $pricing
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to calculate pricing: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Get InPost points for selected addresses
     */
    #[Route('/inpost/points', name: 'inpost_points', methods: ['POST'])]
    public function getInPostPoints(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        try {
            $points = $this->inPostService->getNearbyPoints(
                $data['address'],
                $data['radius'] ?? 5000,
                $data['type'] ?? 'parcel_locker'
            );

            return $this->json([
                'success' => true,
                'points' => $points
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to get InPost points: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Save address to address book
     */
    #[Route('/save-address', name: 'save_address', methods: ['POST'])]
    public function saveAddressToBook(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $customerId = $this->getUser()->getCustomer()->getId();

        try {
            $address = $this->addressBookService->createAddress($customerId, $data);

            return $this->json([
                'success' => true,
                'address_id' => $address['id'],
                'message' => 'Address saved successfully'
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to save address: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Get address suggestions
     */
    #[Route('/address/suggest', name: 'address_suggest', methods: ['POST'])]
    public function getAddressSuggestions(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        try {
            $suggestions = $this->addressBookService->getAddressSuggestions(
                $data['query'],
                $data['country'] ?? 'PL'
            );

            return $this->json([
                'success' => true,
                'suggestions' => $suggestions
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to get suggestions: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Shipment creation success page
     */
    #[Route('/success/{id}', name: 'success', methods: ['GET'])]
    public function success(int $id): Response
    {
        $customerId = $this->getUser()->getCustomer()->getId();
        $shipment = $this->wizardService->getShipmentDetails($customerId, $id);

        if (!$shipment) {
            throw $this->createNotFoundException('Shipment not found');
        }

        return $this->render('customer/shipment_wizard/success.html.twig', [
            'shipment' => $shipment
        ]);
    }
}