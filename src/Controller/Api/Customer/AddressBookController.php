<?php

declare(strict_types=1);

namespace App\Controller\Api\Customer;

use App\Service\AddressBook\AddressBookService;
use App\Service\AddressBook\AddressValidationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Customer Address Book Management API Controller
 * Manages sender and recipient addresses for efficient shipment creation
 */
#[Route('/api/v1/customer/addresses', name: 'api_customer_addresses_')]
#[IsGranted('ROLE_CUSTOMER_USER')]
class AddressBookController extends AbstractController
{
    public function __construct(
        private readonly AddressBookService $addressBookService,
        private readonly AddressValidationService $addressValidator
    ) {
    }

    /**
     * List customer addresses with filtering
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function listAddresses(Request $request): JsonResponse
    {
        $customerId = $this->getUser()->getCustomer()->getId();

        $filters = [
            'type' => $request->query->get('type'), // 'sender', 'recipient', 'both'
            'search' => $request->query->get('search'),
            'country' => $request->query->get('country'),
            'active_only' => $request->query->getBoolean('active_only', true)
        ];

        $pagination = [
            'page' => $request->query->getInt('page', 1),
            'limit' => $request->query->getInt('limit', 50),
            'sort' => $request->query->get('sort', 'name'),
            'order' => $request->query->get('order', 'asc')
        ];

        $result = $this->addressBookService->getCustomerAddresses($customerId, $filters, $pagination);

        return $this->json([
            'success' => true,
            'data' => $result['data'],
            'pagination' => $result['pagination']
        ]);
    }

    /**
     * Get address details
     */
    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getAddress(int $id): JsonResponse
    {
        $customerId = $this->getUser()->getCustomer()->getId();
        $address = $this->addressBookService->getCustomerAddress($customerId, $id);

        if (!$address) {
            return $this->json(['success' => false, 'message' => 'Address not found'], 404);
        }

        return $this->json([
            'success' => true,
            'data' => $address
        ]);
    }

    /**
     * Create new address
     */
    #[Route('', name: 'create', methods: ['POST'])]
    public function createAddress(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $customerId = $this->getUser()->getCustomer()->getId();

        // Validate address data
        $validationResult = $this->addressValidator->validateAddress($data);
        if (!$validationResult['valid']) {
            return $this->json([
                'success' => false,
                'message' => 'Address validation failed',
                'errors' => $validationResult['errors']
            ], 400);
        }

        try {
            $address = $this->addressBookService->createAddress($customerId, $data);

            return $this->json([
                'success' => true,
                'message' => 'Address created successfully',
                'data' => $address
            ], 201);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to create address: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update address
     */
    #[Route('/{id}', name: 'update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function updateAddress(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $customerId = $this->getUser()->getCustomer()->getId();

        $address = $this->addressBookService->getCustomerAddress($customerId, $id);
        if (!$address) {
            return $this->json(['success' => false, 'message' => 'Address not found'], 404);
        }

        // Validate updated address data
        $validationResult = $this->addressValidator->validateAddress($data);
        if (!$validationResult['valid']) {
            return $this->json([
                'success' => false,
                'message' => 'Address validation failed',
                'errors' => $validationResult['errors']
            ], 400);
        }

        try {
            $updatedAddress = $this->addressBookService->updateAddress($id, $data);

            return $this->json([
                'success' => true,
                'message' => 'Address updated successfully',
                'data' => $updatedAddress
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to update address: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete address
     */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function deleteAddress(int $id): JsonResponse
    {
        $customerId = $this->getUser()->getCustomer()->getId();

        $address = $this->addressBookService->getCustomerAddress($customerId, $id);
        if (!$address) {
            return $this->json(['success' => false, 'message' => 'Address not found'], 404);
        }

        try {
            $this->addressBookService->deleteAddress($id);

            return $this->json([
                'success' => true,
                'message' => 'Address deleted successfully'
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to delete address: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate address against courier services
     */
    #[Route('/validate', name: 'validate', methods: ['POST'])]
    public function validateAddress(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $courierService = $data['courier_service'] ?? null;

        try {
            $validationResult = $this->addressValidator->validateAddressForCourier($data, $courierService);

            return $this->json([
                'success' => true,
                'data' => $validationResult
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Address validation failed: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get address suggestions based on partial input
     */
    #[Route('/suggest', name: 'suggest', methods: ['POST'])]
    public function suggestAddresses(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $partialAddress = $data['address'] ?? '';
        $country = $data['country'] ?? 'PL';

        try {
            $suggestions = $this->addressValidator->getAddressSuggestions($partialAddress, $country);

            return $this->json([
                'success' => true,
                'data' => $suggestions
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to get address suggestions: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * Set address as default for specific type
     */
    #[Route('/{id}/set-default', name: 'set_default', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function setDefaultAddress(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $customerId = $this->getUser()->getCustomer()->getId();
        $type = $data['type'] ?? 'sender'; // 'sender' or 'recipient'

        $address = $this->addressBookService->getCustomerAddress($customerId, $id);
        if (!$address) {
            return $this->json(['success' => false, 'message' => 'Address not found'], 404);
        }

        try {
            $this->addressBookService->setDefaultAddress($customerId, $id, $type);

            return $this->json([
                'success' => true,
                'message' => "Address set as default {$type} address"
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to set default address: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import addresses from external source (CSV, etc.)
     */
    #[Route('/import', name: 'import', methods: ['POST'])]
    public function importAddresses(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $customerId = $this->getUser()->getCustomer()->getId();
        $addresses = $data['addresses'] ?? [];
        $overwriteExisting = $data['overwrite_existing'] ?? false;

        if (empty($addresses)) {
            return $this->json([
                'success' => false,
                'message' => 'No addresses provided for import'
            ], 400);
        }

        try {
            $result = $this->addressBookService->importAddresses($customerId, $addresses, $overwriteExisting);

            return $this->json([
                'success' => true,
                'message' => 'Addresses imported successfully',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to import addresses: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export addresses to various formats
     */
    #[Route('/export', name: 'export', methods: ['GET'])]
    public function exportAddresses(Request $request): JsonResponse
    {
        $customerId = $this->getUser()->getCustomer()->getId();
        $format = $request->query->get('format', 'json'); // json, csv, excel
        $type = $request->query->get('type'); // sender, recipient, both

        try {
            $exportData = $this->addressBookService->exportAddresses($customerId, $format, $type);

            return $this->json([
                'success' => true,
                'data' => $exportData
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to export addresses: ' . $e->getMessage()
            ], 500);
        }
    }
}