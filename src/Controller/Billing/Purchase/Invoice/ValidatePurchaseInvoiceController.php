<?php

namespace App\Controller\Billing\Purchase\Invoice;

use App\Entity\Billing\Purchase\PurchaseInvoice;
use App\Entity\Security\User;
use App\Repository\Billing\Purchase\PurchaseInvoiceItemRepository;
use App\Repository\Billing\Purchase\PurchaseInvoiceItemStockRepository;
use App\Repository\Billing\Purchase\PurchaseInvoiceRepository;
use App\Repository\Inventory\StockRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[AsController]
class ValidatePurchaseInvoiceController extends AbstractController
{
    public function __construct(private readonly TokenStorageInterface $tokenStorage,
    )
    {
    }

    public function __invoke(PurchaseInvoiceItemRepository $purchaseInvoiceItemRepository,
                             PurchaseInvoiceRepository $purchaseInvoiceRepository,
                             PurchaseInvoiceItemStockRepository $purchaseInvoiceItemStockRepository,
                             EntityManagerInterface $entityManager,
                             StockRepository $stockRepository,
                             Request $request): JsonResponse
    {

        $id = $request->get('id');

        $purchaseInvoice = $purchaseInvoiceRepository->find($id);
        if(!$purchaseInvoice instanceof PurchaseInvoice)
        {
            // Warning
            return new JsonResponse(['hydra:description' => 'This data must be type of invoice.'], 404);
        }

        if(!$purchaseInvoice)
        {
            // Warning
            return new JsonResponse(['hydra:description' => 'Invoice not found.'], 404);
        }

        $purchaseInvoiceItem = $purchaseInvoiceItemRepository->findOneBy(['purchaseInvoice' => $purchaseInvoice]);
        if(!$purchaseInvoiceItem)
        {
            // Warning
            return new JsonResponse(['hydra:description' => 'Cannot proceed : the cart is empty.'], 404);
        }

        $purchaseInvoice->setStatus('invoice');

        // Update purchase invoice amount
        $amount = $purchaseInvoiceItemRepository->purchaseInvoiceHtAmount($purchaseInvoice)[0][1];
        $purchaseInvoice->setAmount($amount);

        $taxResult = 0;
        $discountAmount = 0;

        $purchaseInvoiceItems = $purchaseInvoiceItemRepository->findBy(['purchaseInvoice' => $purchaseInvoice]);
        foreach ($purchaseInvoiceItems as $purchaseInvoiceItem){
            if ($purchaseInvoiceItem->getTaxes()){
                foreach ($purchaseInvoiceItem->getTaxes() as $tax){
                    $taxResult = $taxResult + $purchaseInvoiceItem->getAmount() * $tax->getRate() / 100;
                }
            }

            $discountAmount = $discountAmount + $purchaseInvoiceItem->getAmount() * $purchaseInvoiceItem->getDiscount() / 100;
        }

        $amountTtc = $purchaseInvoiceItemRepository->purchaseInvoiceHtAmount($purchaseInvoice)[0][1] + $taxResult - $discountAmount;

        $purchaseInvoice->setTtc($amountTtc);
        $purchaseInvoice->setBalance($amountTtc);
        $purchaseInvoice->setVirtualBalance($amountTtc);

        // Update purchase invoice amount end

        // Validate purchase invoice item stock : reserved quantity
        /*foreach ($purchaseInvoiceItems as $purchaseInvoiceItem)
        {
            // Get purchase invoice item stock
            $purchaseInvoiceItemStocks = $purchaseInvoiceItemStockRepository->findBy(['purchaseInvoiceItem' => $purchaseInvoiceItem]);

            if($purchaseInvoiceItemStocks)
            {
                foreach ($purchaseInvoiceItemStocks as $purchaseInvoiceItemStock){

                    $stock = $purchaseInvoiceItemStock->getStock();

                    $stock->setReserveQte($stock->getReserveQte() + $purchaseInvoiceItemStock->getQuantity());
                    $stock->setAvailableQte($stock->getAvailableQte() - $purchaseInvoiceItemStock->getQuantity());
                    $stock->setQuantity(($stock->getAvailableQte() - $purchaseInvoiceItemStock->getQuantity()) + ($stock->getReserveQte() + $purchaseInvoiceItemStock->getQuantity()));

                }

            }

        }*/

        $entityManager->flush();

        return $this->json(['hydra:member' => '200']);

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
