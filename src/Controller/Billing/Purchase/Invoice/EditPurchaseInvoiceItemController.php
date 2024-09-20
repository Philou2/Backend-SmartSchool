<?php

namespace App\Controller\Billing\Purchase\Invoice;

use App\Entity\Security\User;
use App\Repository\Billing\Purchase\PurchaseInvoiceItemRepository;
use App\Repository\Billing\Purchase\PurchaseInvoiceItemStockRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[AsController]
class EditPurchaseInvoiceItemController extends AbstractController
{
    public function __construct(private readonly TokenStorageInterface $tokenStorage, private readonly EntityManagerInterface $entityManager)
    {
    }

    #[Route('api/edit/purchase-invoice/item/{id}', name: 'edit_purchase_invoice_item')]
    public function editItem($id, PurchaseInvoiceItemRepository $purchaseInvoiceItemRepository, PurchaseInvoiceItemStockRepository $purchaseInvoiceItemStockRepository, Request $request): JsonResponse
    {
        $request = Request::createFromGlobals();

        $purchaseInvoiceItemData = json_decode($request->getContent(), true);

        $purchaseInvoiceItem = $purchaseInvoiceItemRepository->findOneBy(['id' => $id]);
        if (!$purchaseInvoiceItem){
            return new JsonResponse(['hydra:description' => 'This purchase invoice item '.$id.' is not found.'], 404);
        }

        $purchaseInvoiceItemStock = $purchaseInvoiceItemStockRepository->findOneBy(['purchaseInvoiceItem' => $purchaseInvoiceItem]);
        if (!$purchaseInvoiceItemStock){
            return new JsonResponse(['hydra:description' => 'Purchase invoice item stock not found.'], 404);
        }

        // receive data from url
        if (!is_numeric($purchaseInvoiceItemData['quantity'])){
            return new JsonResponse(['hydra:description' => 'Quantity must be numeric value.'], 500);
        }
        if ($purchaseInvoiceItemData['quantity'] <= 0){
            return new JsonResponse(['hydra:description' => 'Quantity must be upper than 0.'], 500);
        }
        if (isset($purchaseInvoiceItemData['pu']) && !is_numeric($purchaseInvoiceItemData['pu'])){
            return new JsonResponse(['hydra:description' => 'Pu must be numeric value.'], 500);
        }
        if (isset($purchaseInvoiceItemData['pu']) && $purchaseInvoiceItemData['pu'] <= 0){
            return new JsonResponse(['hydra:description' => 'Pu must be upper than 0.'], 500);
        }
        /*if (isset($purchaseInvoiceItemData['discount']) && $purchaseInvoiceItemData['discount'] < 0){
            return new JsonResponse(['hydra:description' => 'Discount must be positive value.'], 500);
        }*/

        $stock = $purchaseInvoiceItemStock->getStock();
        if ($purchaseInvoiceItemData['quantity'] > $stock->getAvailableQte()){
            return new JsonResponse(['hydra:description' => 'Request quantity must be less than available quantity.'], 500);
        }

        // get discount
        $discount = $purchaseInvoiceItem->getDiscount();

        // get taxes
        $taxes = $purchaseInvoiceItem->getTaxes();

        // update purchase invoice item data : quantity - pu - discount

        if (isset($purchaseInvoiceItemData['quantity'])){
            $purchaseInvoiceItem->setQuantity($purchaseInvoiceItemData['quantity']);
        }
        if (isset($purchaseInvoiceItemData['pu'])){
            $purchaseInvoiceItem->setPu($purchaseInvoiceItemData['pu']);
        }
        $purchaseInvoiceItem->setName($purchaseInvoiceItemData['name']);

        $purchaseInvoiceItem->setAmount($purchaseInvoiceItemData['quantity'] * $purchaseInvoiceItemData['pu']);

        $taxResult = 0;
        if ($taxes){
            foreach ($taxes as $tax){
                $taxResult = $taxResult + ($purchaseInvoiceItemData['quantity'] * $purchaseInvoiceItemData['pu'] * $tax->getRate() / 100);
            }
        }

        $discountAmount =  $purchaseInvoiceItemData['quantity'] * $purchaseInvoiceItemData['pu'] * $discount / 100;
        $purchaseInvoiceItem->setDiscountAmount($discountAmount);
        $purchaseInvoiceItem->setAmountTtc(($purchaseInvoiceItemData['quantity'] * $purchaseInvoiceItemData['pu']) + $taxResult - $discountAmount);
        $purchaseInvoiceItem->setAmountWithTaxes($taxResult);

        // update purchase invoice item data : quantity - pu - discount end

        // update purchase invoice item stock
        $purchaseInvoiceItemStock->setQuantity($purchaseInvoiceItemData['quantity']);

        $this->entityManager->flush();

        return $this->json(['hydra:member' => $purchaseInvoiceItem]);

    }

    public function getIdFromApiResourceId(string $apiId){
        $lastIndexOf = strrpos($apiId, '/');
        $id = substr($apiId, $lastIndexOf+1);
        return intval($id);
    }
    public function getUser(): ?User
    {
        $token = $this->tokenStorage->getToken();

        if (!$token) {
            return null;
        }

        $user = $token->getUser();

        if (!$user instanceof User) {
            return null;
        }

        return $user;
    }

}
