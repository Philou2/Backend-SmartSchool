<?php
namespace App\State\Processor\Billing\Purchase;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Billing\Purchase\PurchaseInvoice;
use App\Entity\Billing\Purchase\PurchaseInvoiceItem;
use App\Entity\Billing\Purchase\PurchaseInvoiceItemStock;
use App\Entity\Billing\Sale\SaleInvoice;
use App\Entity\Billing\Sale\SaleInvoiceItem;
use App\Entity\Billing\Sale\SaleInvoiceItemStock;
use App\Entity\Security\User;
use App\Repository\Billing\Purchase\PurchaseInvoiceItemStockRepository;
use App\Repository\Billing\Purchase\PurchaseInvoiceRepository;
use App\Repository\Billing\Sale\SaleInvoiceRepository;
use App\Repository\Inventory\StockRepository;
use App\Repository\Setting\Finance\TaxRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class CreatePurchaseInvoiceItemProcessor implements ProcessorInterface
{

    public function __construct(private readonly ProcessorInterface $processor,
                                private readonly TokenStorageInterface $tokenStorage,
                                private readonly Request $request,
                                private readonly EntityManagerInterface $manager,
                                private readonly TaxRepository $taxRepository,
                                private readonly StockRepository $stockRepository,
                                private readonly PurchaseInvoiceItemStockRepository $purchaseInvoiceItemStockRepository,
                                private readonly PurchaseInvoiceRepository $purchaseInvoiceRepository) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $invoiceData = json_decode($this->request->getContent(), true);

        $purchaseInvoice = $this->purchaseInvoiceRepository->find($data->getId());

        if(!$data instanceof PurchaseInvoice)
        {
            // Warning
            return new JsonResponse(['hydra:description' => 'This invoice is not found.'], 404);
        }

        if (!is_numeric($invoiceData['quantity'])){
            return new JsonResponse(['hydra:description' => 'Quantity must be numeric value.'], 500);
        }

        if ($invoiceData['quantity'] <= 0){
            return new JsonResponse(['hydra:description' => 'Quantity must be upper than 0.'], 500);
        }

        if (!is_numeric($invoiceData['pu'])){
            return new JsonResponse(['hydra:description' => 'Pu must be numeric value.'], 500);
        }

        if ($invoiceData['pu'] <= 0){
            return new JsonResponse(['hydra:description' => 'Pu must be upper than 0.'], 500);
        }

        if ($invoiceData['discount'] < 0){
            return new JsonResponse(['hydra:description' => 'Discount must be positive value.'], 500);
        }

        $stock = $this->stockRepository->find($this->getIdFromApiResourceId($invoiceData['item']));

        if (!$stock){
            return new JsonResponse(['hydra:description' => 'Stock not found!'], 500);
        }

        if ($invoiceData['quantity'] > $stock->getAvailableQte()){
            return new JsonResponse(['hydra:description' => 'Request quantity must be less than available quantity.'], 500);
        }

        $amount = $invoiceData['quantity'] * $invoiceData['pu'];

        $purchaseInvoiceItem = new PurchaseInvoiceItem();

        $purchaseInvoiceItem->setItem($stock->getItem());
        $purchaseInvoiceItem->setQuantity($invoiceData['quantity']);
        $purchaseInvoiceItem->setPu($invoiceData['pu']);
        $purchaseInvoiceItem->setDiscount($invoiceData['discount']);
        $purchaseInvoiceItem->setPurchaseInvoice($purchaseInvoice);
        $purchaseInvoiceItem->setName($invoiceData['name']);
        $purchaseInvoiceItem->setAmount($amount);

        $taxResult = 0;
        if (isset($invoiceData['taxes'])){
            foreach ($invoiceData['taxes'] as $tax){
                $taxObject = $this->taxRepository->find($this->getIdFromApiResourceId($tax));
                $purchaseInvoiceItem->addTax($taxObject);
                $taxResult = $taxResult + $purchaseInvoiceItem->getAmount() * $taxObject->getRate() / 100;
            }
        }

        if($invoiceData['discount'] > 0)
        {
            // there is a discount to add
            $discountAmount =  ($amount * $invoiceData['discount']) / 100;
        }
        else{
            // there is no discount to add
            $discountAmount =  0;
        }

        $purchaseInvoiceItem->setDiscountAmount($discountAmount);
        $purchaseInvoiceItem->setAmountTtc($purchaseInvoiceItem->getAmount() + $taxResult - $discountAmount);
        $purchaseInvoiceItem->setAmountWithTaxes($taxResult);

        $purchaseInvoiceItem->setUser($this->getUser());
        $purchaseInvoiceItem->setBranch($this->getUser()->getBranch());
        $purchaseInvoiceItem->setInstitution($this->getUser()->getInstitution());
        $purchaseInvoiceItem->setYear($this->getUser()->getCurrentYear());

        $this->manager->persist($purchaseInvoiceItem);

        // CHECK IF THAT STOCK IS ALREADY IN CURRENT SALE INVOICE
        /*$saleInvoiceItemStock = $this->saleInvoiceItemStockRepository->findOneBy(['stock' => $stock, 'saleInvoice' => $saleInvoice]);

        if($saleInvoiceItemStock)
        {
            $saleInvoiceItem = $saleInvoiceItemStock->getSaleInvoiceItem();
            $qty = $saleInvoiceItem->getQuantity() + 1;

            // Update Sale Invoice Item
            $saleInvoiceItem->setQuantity($qty);
            $saleInvoiceItem->setAmountTtc($saleInvoiceItem->getPu() * $qty);
            $saleInvoiceItem->setAmount($saleInvoiceItem->getPu() * $qty);
        }
        else
        {

        }*/

        $purchaseInvoiceItemStock = new PurchaseInvoiceItemStock();
        $purchaseInvoiceItemStock->setPurchaseInvoiceItem($purchaseInvoiceItem);
        $purchaseInvoiceItemStock->setStock($stock);
        $purchaseInvoiceItemStock->setQuantity($invoiceData['quantity']);
        $purchaseInvoiceItemStock->setCreatedAt(new \DateTimeImmutable());
        $purchaseInvoiceItemStock->setUser($this->getUser());
        $purchaseInvoiceItemStock->setYear($this->getUser()->getCurrentYear());
        $purchaseInvoiceItemStock->setBranch($this->getUser()->getBranch());
        $purchaseInvoiceItemStock->setInstitution($this->getUser()->getInstitution());

        $this->manager->persist($purchaseInvoiceItemStock);

        // get the outStrategy
        // $outStrategy = $saleInvoiceItem->getItem()?->getItemCategory()?->getStockStrategy()?->getCode();

        /*if($outStrategy == 'FIFO')
        {
            // FIFO
            // find all related item stock

            $stock = $this->stockRepository->findOneAvailableStockGreaterThanZeroByItemStoreAsc($saleInvoiceItem->getItem()) ? $this->stockRepository->findOneAvailableStockGreaterThanZeroByItemStoreAsc($saleInvoiceItem->getItem())[0] : '';
        }
        else{
            // LIFO
            // find all related item stock
            $stock = $this->stockRepository->findOneAvailableStockGreaterThanZeroByItemStoreDesc($saleInvoiceItem->getItem()) ? $this->stockRepository->findOneAvailableStockGreaterThanZeroByItemStoreDesc($saleInvoiceItem->getItem())[0] : '';
        }*/

        /*if($stock)
        {
            $saleInvoiceItemStock = $this->saleInvoiceItemStockRepository->findOneBy(['saleInvoiceItem' => $saleInvoiceItem, 'stock' => $stock]);
            if ($saleInvoiceItemStock)
            {
                $saleInvoiceItemStock->setQuantity($saleInvoiceItemStock->getQuantity() + 1);
                $saleInvoiceItemStock->setCreatedAt(new \DateTimeImmutable());
            }
            else {
                $saleInvoiceItemStock = new SaleInvoiceItemStock();
                $saleInvoiceItemStock->setSaleInvoiceItem($saleInvoiceItem);
                $saleInvoiceItemStock->setStock($stock);
                $saleInvoiceItemStock->setQuantity(1);
                $saleInvoiceItemStock->setCreatedAt(new \DateTimeImmutable());
                $saleInvoiceItemStock->setUser($this->getUser());
                $saleInvoiceItemStock->setYear($this->getUser()->getCurrentYear());
                $saleInvoiceItemStock->setBranch($this->getUser()->getBranch());
                $saleInvoiceItemStock->setInstitution($this->getUser()->getInstitution());

                $this->manager->persist($saleInvoiceItemStock);
            }

        }
        else{
            // item not in stock
            return new JsonResponse(['hydra:description' => 'No item found in stock for this criteria'], 404);

        }*/

        $this->manager->flush();

        return $this->processor->process($purchaseInvoiceItem, $operation, $uriVariables, $context);
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
