<?php

namespace App\Controller\Billing\Purchase\Reception;

use App\Entity\Billing\Purchase\PurchaseInvoice;
use App\Entity\Inventory\Stock;
use App\Entity\Inventory\StockMovement;
use App\Entity\Security\User;
use App\Repository\Billing\Purchase\PurchaseInvoiceItemRepository;
use App\Repository\Billing\Purchase\PurchaseInvoiceItemStockRepository;
use App\Repository\Billing\Purchase\PurchaseInvoiceRepository;
use App\Repository\Inventory\LocationRepository;
use App\Repository\Inventory\StockRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[AsController]
class CreatePurchaseInvoiceStockInController extends AbstractController
{
    public function __construct(private readonly TokenStorageInterface $tokenStorage,
                                private readonly EntityManagerInterface $manager
    )
    {
    }

    public function __invoke(PurchaseInvoiceItemRepository $purchaseInvoiceItemRepository,
                             PurchaseInvoiceItemStockRepository $purchaseInvoiceItemStockRepository,
                             PurchaseInvoiceRepository $purchaseInvoiceRepository,
                             StockRepository $stockRepository,
                             LocationRepository $locationRepository,
                             EntityManagerInterface $entityManager,
                             Request $request): JsonResponse
    {

        $id = $request->get('id');

        $invoice = $purchaseInvoiceRepository->find($id);

        if(!($invoice instanceof PurchaseInvoice))
        {
            // Warning
            return new JsonResponse(['hydra:title' => 'This data must be type of invoice.'], 404);
        }

        $purchaseInvoiceItems = $purchaseInvoiceItemRepository->findBy(['purchaseInvoice' => $invoice]);
        if ($purchaseInvoiceItems){
            foreach ($purchaseInvoiceItems as $purchaseInvoiceItem){

                // Faire l'entré en stock

                $stockRef = $stockRepository->findOneBy([], ['id' => 'DESC']);
                if (!$stockRef){
                    $reference = 'WH/ST/' . str_pad( 1, 5, '0', STR_PAD_LEFT);
                }
                else{
                    $filterNumber = preg_replace("/[^0-9]/", '', $stockRef->getReference());
                    $number = intval($filterNumber);

                    // Utilisation de number_format() pour ajouter des zéros à gauche
                    $reference = 'WH/ST/' . str_pad($number + 1, 5, '0', STR_PAD_LEFT);
                }

                $location = $locationRepository->findOneBy(['branch' => $this->getUser()->getBranch()]);

                $stock = new Stock();

                $stock->setReference($reference);

                $stock->setItem($purchaseInvoiceItem->getItem());
                $stock->setQuantity($purchaseInvoiceItem->getQuantity());
                $stock->setAvailableQte($purchaseInvoiceItem->getQuantity());
                $stock->setReserveQte(0);

                $stock->setUnitCost($purchaseInvoiceItem->getItem()->getPrice());
                $stock->setTotalValue($purchaseInvoiceItem->getItem()->getPrice() * $purchaseInvoiceItem->getQuantity());

                $stock->setWarehouse($location->getWarehouse());
                $stock->setLocation($location);

                $stock->setStockAt(new \DateTimeImmutable());

                $stock->setNote('purchase stock in from '.$invoice->getInvoiceNumber());

                $stock->setBranch($this->getUser()->getBranch());
                $stock->setUser($this->getUser());

                $stock->setInstitution($this->getUser()->getInstitution());
                $stock->setYear($this->getUser()->getCurrentYear());

                $entityManager->persist($stock);


                // Stock movement
                $stockMovement = new StockMovement();
                $stockMovement->setReference($stock->getReference());
                $stockMovement->setItem($purchaseInvoiceItem->getItem());
                $stockMovement->setQuantity($purchaseInvoiceItem->getQuantity());
                $stockMovement->setUnitCost($stock->getUnitCost());
                $stockMovement->setFromWarehouse($stock->getWarehouse());
                // from location
                // to warehouse
                // to location
                $stockMovement->setStockAt(new \DateTimeImmutable());
                $stockMovement->setLoseAt($stock->getLoseAt());
                $stockMovement->setNote('purchase stock in');
                $stockMovement->setIsOut(true);

                $stockMovement->setStock($stock);

                $stockMovement->setYear($this->getUser()->getCurrentYear());
                $stockMovement->setUser($this->getUser());
                $stockMovement->setCreatedAt(new \DateTimeImmutable());
                $stockMovement->setIsEnable(true);
                $stockMovement->setUpdatedAt(new \DateTimeImmutable());
                $stockMovement->setInstitution($this->getUser()->getInstitution());

                $entityManager->persist($stockMovement);

            }
        }

        $invoice->setOtherStatus('stock in');

        $this->manager->flush();

        return $this->json(['hydra:member' => $invoice]);
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
