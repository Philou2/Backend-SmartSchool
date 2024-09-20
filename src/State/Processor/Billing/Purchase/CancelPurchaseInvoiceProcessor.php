<?php
namespace App\State\Processor\Billing\Purchase;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Billing\Purchase\PurchaseInvoice;
use App\Entity\Billing\Sale\SaleInvoice;
use App\Repository\Billing\Purchase\PurchaseInvoiceItemRepository;
use App\Repository\Billing\Purchase\PurchaseInvoiceItemStockRepository;
use App\Repository\Billing\Purchase\PurchaseInvoiceRepository;
use App\Repository\Billing\Sale\SaleInvoiceItemRepository;
use App\Repository\Billing\Sale\SaleInvoiceItemStockRepository;
use App\Repository\Billing\Sale\SaleInvoiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

final class CancelPurchaseInvoiceProcessor implements ProcessorInterface
{

    public function __construct(private readonly ProcessorInterface $processor,
                                private readonly EntityManagerInterface $manager,
                                Private readonly PurchaseInvoiceRepository $purchaseInvoiceRepository,
                                Private readonly PurchaseInvoiceItemRepository $purchaseInvoiceItemRepository,
                                Private readonly PurchaseInvoiceItemStockRepository $purchaseInvoiceItemStockRepository
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {

        if(!$data instanceof PurchaseInvoice)
        {
            // Warning
            return new JsonResponse(['hydra:description' => 'This data must be type of invoice.'], 404);
        }

        $purchaseInvoice = $this->purchaseInvoiceRepository->find($data->getId());
        if(!$purchaseInvoice)
        {
            // Warning
            return new JsonResponse(['hydra:description' => 'Invoice not found.'], 404);
        }

        $purchaseInvoice->setStatus('draft');

        // Validate purchase invoice item stock : reserved quantity
        $purchaseInvoiceItems = $this->purchaseInvoiceItemRepository->findBy(['purchaseInvoice' => $purchaseInvoice]);
        foreach ($purchaseInvoiceItems as $purchaseInvoiceItem)
        {
            // Get purchase invoice item stock
            $purchaseInvoiceItemStocks = $this->purchaseInvoiceItemStockRepository->findBy(['purchaseInvoiceItem' => $purchaseInvoiceItem]);

            if($purchaseInvoiceItemStocks)
            {
                foreach ($purchaseInvoiceItemStocks as $purchaseInvoiceItemStock){

                    $stock = $purchaseInvoiceItemStock->getStock();

                    $stock->setReserveQte($stock->getReserveQte() - $purchaseInvoiceItemStock->getQuantity());
                    $stock->setAvailableQte($stock->getAvailableQte() + $purchaseInvoiceItemStock->getQuantity());
                    $stock->setQuantity(($stock->getAvailableQte() + $purchaseInvoiceItemStock->getQuantity()) + ($stock->getReserveQte() - $purchaseInvoiceItemStock->getQuantity()));

                }

            }

        }

        $this->manager->flush();

        return $this->processor->process($purchaseInvoice, $operation, $uriVariables, $context);

    }
}
