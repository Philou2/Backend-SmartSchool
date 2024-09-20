<?php

namespace App\Controller\Billing\Sale\Invoice;

use App\Entity\Billing\Sale\SaleInvoice;
use App\Entity\Security\User;
use App\Repository\Billing\Sale\SaleInvoiceItemRepository;
use App\Repository\Billing\Sale\SaleInvoiceItemStockRepository;
use App\Repository\Billing\Sale\SaleInvoiceRepository;
use App\Repository\Inventory\StockRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[AsController]
class ValidateSaleInvoiceController extends AbstractController
{
    public function __construct(private readonly TokenStorageInterface $tokenStorage)
    {
    }

    public function __invoke(SaleInvoiceItemRepository $saleInvoiceItemRepository,
                             SaleInvoiceRepository $saleInvoiceRepository,
                             SaleInvoiceItemStockRepository $saleInvoiceItemStockRepository,
                             StockRepository $stockRepository,
                             EntityManagerInterface $entityManager,
                             Request $request): JsonResponse
    {
        $id = $request->get('id');

        $saleInvoice = $saleInvoiceRepository->find($id);
        if(!$saleInvoice instanceof SaleInvoice)
        {
            return new JsonResponse(['hydra:description' => 'This data must be type of invoice.'], 404);
        }

        if(!$saleInvoice->getCustomer())
        {
            return new JsonResponse(['hydra:description' => 'Choose a customer and save before validate.'], 404);
        }

        $saleInvoiceItem = $saleInvoiceItemRepository->findOneBy(['saleInvoice' => $saleInvoice]);
        if(!$saleInvoiceItem)
        {
            return new JsonResponse(['hydra:description' => 'Can not validate with empty cart.'], 404);
        }

        $saleInvoice->setStatus('invoice');

        // Validate sale invoice item stock : reserved quantity
        $saleInvoiceItems = $saleInvoiceItemRepository->findBy(['saleInvoice' => $saleInvoice]);
        foreach ($saleInvoiceItems as $saleInvoiceItem)
        {
            // Get sale invoice item stock
            $saleInvoiceItemStocks = $saleInvoiceItemStockRepository->findBy(['saleInvoiceItem' => $saleInvoiceItem]);

            if($saleInvoiceItemStocks)
            {
                foreach ($saleInvoiceItemStocks as $saleInvoiceItemStock){

                    $stock = $saleInvoiceItemStock->getStock();

                    $stock->setReserveQte($stock->getReserveQte() + $saleInvoiceItemStock->getQuantity());
                    $stock->setAvailableQte($stock->getAvailableQte() - $saleInvoiceItemStock->getQuantity());
                    $stock->setQuantity(($stock->getAvailableQte() - $saleInvoiceItemStock->getQuantity()) + ($stock->getReserveQte() + $saleInvoiceItemStock->getQuantity()));

                }

            }

        }

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
