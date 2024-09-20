<?php

namespace App\Controller\Billing\Sale\Delivery;

use App\Entity\Billing\Sale\SaleInvoice;
use App\Entity\Inventory\StockMovement;
use App\Entity\Security\User;
use App\Repository\Billing\Sale\SaleInvoiceItemRepository;
use App\Repository\Billing\Sale\SaleInvoiceItemStockRepository;
use App\Repository\Billing\Sale\SaleInvoiceRepository;
use App\Repository\Inventory\DeliveryItemRepository;
use App\Repository\Inventory\DeliveryRepository;
use App\Repository\Inventory\StockMovementRepository;
use App\Repository\Inventory\StockRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[AsController]
class CreateSaleInvoiceStockOutController extends AbstractController
{
    public function __construct(private readonly TokenStorageInterface $tokenStorage, private readonly EntityManagerInterface $manager)
    {
    }

    public function __invoke(SaleInvoiceItemRepository $saleInvoiceItemRepository,
                             SaleInvoiceItemStockRepository $saleInvoiceItemStockRepository,
                             SaleInvoiceRepository $saleInvoiceRepository,
                             DeliveryRepository $deliveryRepository,
                             DeliveryItemRepository $deliveryItemRepository,
                             StockRepository $stockRepository,
                             StockMovementRepository $stockMovementRepository,
                             EntityManagerInterface $entityManager,
                             Request $request): JsonResponse
    {

        $id = $request->get('id');

        $data = json_decode($request->getContent(), true);

        $saleInvoice = $saleInvoiceRepository->find($id);

        if(!($saleInvoice instanceof SaleInvoice))
        {
            return new JsonResponse(['hydra:title' => 'This data must be type of invoice.'], 404);
        }

        $existingDelivery = $deliveryRepository->findOneBy(['saleInvoice' => $saleInvoice]);
        if ($existingDelivery){
            return new JsonResponse(['hydra:title' => 'This invoice already has delivery on it.'], 500);
        }

        $saleInvoiceItems = $saleInvoiceItemRepository->findBy(['saleInvoice' => $saleInvoice]);
        if ($saleInvoiceItems)
        {
            foreach ($saleInvoiceItems as $saleInvoiceItem)
            {
                // Faire la sortie de stock
                $saleInvoiceItemStocks = $saleInvoiceItemStockRepository->findBy(['saleInvoiceItem' => $saleInvoiceItem]);
                if ($saleInvoiceItemStocks)
                {
                    foreach ($saleInvoiceItemStocks as $saleInvoiceItemStock)
                    {
                        $stock = $saleInvoiceItemStock->getStock();

                        $stock->setReserveQte($stock->getReserveQte() - $saleInvoiceItemStock->getQuantity());
                        $stock->setQuantity($stock->getQuantity() - $saleInvoiceItemStock->getQuantity());

                        // Stock movement
                        $stockMovement = new StockMovement();

                        $stockOutRef = $stockMovementRepository->findOneBy(['isOut' => true, 'branch' => $this->getUser()->getBranch()], ['id' => 'DESC']);
                        if (!$stockOutRef){
                            $reference = 'WH/OUT/' . str_pad( 1, 5, '0', STR_PAD_LEFT);
                        }
                        else{
                            $filterNumber = preg_replace("/[^0-9]/", '', $stockOutRef->getReference());
                            $number = intval($filterNumber);

                            // Utilisation de number_format() pour ajouter des zéros à gauche
                            $reference = 'WH/OUT/' . str_pad($number + 1, 5, '0', STR_PAD_LEFT);
                        }

                        $stockMovement->setReference($reference);
                        $stockMovement->setItem($stock->getItem());
                        $stockMovement->setQuantity($saleInvoiceItemStock->getQuantity());
                        $stockMovement->setUnitCost($stock->getUnitCost());
                        $stockMovement->setFromWarehouse($stock->getWarehouse());
                        // from location
                        // to warehouse
                        // to location
                        $stockMovement->setStockAt(new \DateTimeImmutable());
                        $stockMovement->setLoseAt($stock->getLoseAt());
                        $stockMovement->setNote('sale invoice stock out');
                        $stockMovement->setNote('sale invoice stock out');
                        $stockMovement->setIsOut(true);
                        $stockMovement->setStock($stock);

                        $stockMovement->setYear($this->getUser()->getCurrentYear());
                        $stockMovement->setUser($this->getUser());
                        $stockMovement->setCreatedAt(new \DateTimeImmutable());
                        $stockMovement->setIsEnable(true);
                        $stockMovement->setUpdatedAt(new \DateTimeImmutable());
                        $stockMovement->setBranch($this->getUser()->getBranch());
                        $stockMovement->setInstitution($this->getUser()->getInstitution());

                        $entityManager->persist($stockMovement);
                    }
                }

            }
        }

        $saleInvoice->setOtherStatus('stock out');

        $this->manager->flush();

        return $this->json(['hydra:member' => $saleInvoice]);
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
