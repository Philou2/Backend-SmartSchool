<?php
namespace App\State\Processor\Billing\Purchase;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Billing\Purchase\PurchaseInvoice;
use App\Entity\Billing\Sale\SaleInvoice;
use App\Repository\Billing\Purchase\PurchaseInvoiceItemRepository;
use App\Repository\Billing\Purchase\PurchaseInvoiceItemStockRepository;
use App\Repository\Billing\Purchase\PurchaseInvoiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

final class DeletePurchaseInvoiceProcessor implements ProcessorInterface
{

    public function __construct(private readonly ProcessorInterface $processor,
                                private readonly EntityManagerInterface $manager,
                                private readonly PurchaseInvoiceItemRepository $purchaseInvoiceItemRepository,
                                private readonly PurchaseInvoiceItemStockRepository $purchaseInvoiceItemStockRepository,
                                Private readonly PurchaseInvoiceRepository $purchaseInvoiceRepository) {
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

        $purchaseInvoiceItems = $this->purchaseInvoiceItemRepository->findBy(['purchaseInvoice'=> $purchaseInvoice]);
        if($purchaseInvoiceItems)
        {
            foreach ($purchaseInvoiceItems as $purchaseInvoiceItem)
            {
                $purchaseInvoiceItemStocks = $this->purchaseInvoiceItemStockRepository->findBy(['purchaseInvoiceItem'=> $purchaseInvoiceItem]);
                if($purchaseInvoiceItemStocks)
                {
                    foreach ($purchaseInvoiceItemStocks as $purchaseInvoiceItemStock)
                    {
                        $this->manager->remove($purchaseInvoiceItemStock);
                    }
                }

                $this->manager->remove($purchaseInvoiceItem);
            }
        }

        $this->manager->remove($purchaseInvoice);
        $this->manager->flush();

        //return $this->processor->process($purchaseInvoice, $operation, $uriVariables, $context);

    }
}
