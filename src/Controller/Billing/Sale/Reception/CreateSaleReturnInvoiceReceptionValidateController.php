<?php

namespace App\Controller\Billing\Sale\Reception;

use App\Entity\Billing\Sale\SaleReturnInvoice;
use App\Entity\Inventory\Reception;
use App\Entity\Inventory\ReceptionItem;
use App\Entity\Inventory\ReceptionItemStock;
use App\Entity\Security\User;
use App\Repository\Billing\Sale\SaleReturnInvoiceItemRepository;
use App\Repository\Billing\Sale\SaleReturnInvoiceItemStockRepository;
use App\Repository\Billing\Sale\SaleReturnInvoiceRepository;
use App\Repository\Inventory\ReceptionItemRepository;
use App\Repository\Inventory\ReceptionRepository;
use App\Repository\Inventory\StockRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[AsController]
class CreateSaleReturnInvoiceReceptionValidateController extends AbstractController
{
    public function __construct(private readonly TokenStorageInterface $tokenStorage, private readonly EntityManagerInterface $manager)
    {
    }

    public function __invoke(SaleReturnInvoiceItemRepository $saleReturnInvoiceItemRepository,
                             SaleReturnInvoiceItemStockRepository $saleReturnInvoiceItemStockRepository,
                             SaleReturnInvoiceRepository $saleReturnInvoiceRepository,
                             ReceptionRepository $receptionRepository,
                             ReceptionItemRepository $receptionItemRepository,
                             StockRepository $stockRepository,
                             EntityManagerInterface $entityManager,
                             Request $request): JsonResponse
    {
        $id = $request->get('id');

        $data = json_decode($request->getContent(), true);

        $saleReturnInvoice = $saleReturnInvoiceRepository->find($id);

        if(!($saleReturnInvoice instanceof SaleReturnInvoice))
        {
            return new JsonResponse(['hydra:title' => 'This data must be type of invoice.'], 404);
        }

        $existingReception = $receptionRepository->findOneBy(['saleReturnInvoice' => $saleReturnInvoice]);
        if ($existingReception){
            return new JsonResponse(['hydra:title' => 'This sale return invoice already has reception on it.'], 500);
        }

        $generateReceptionUniqNumber = $receptionRepository->findOneBy(['branch' => $this->getUser()->getBranch()], ['id' => 'DESC']);
        if (!$generateReceptionUniqNumber){
            $uniqueNumber = 'SAL/RET/REC/' . str_pad( 1, 5, '0', STR_PAD_LEFT);
        }
        else{
            $filterNumber = preg_replace("/[^0-9]/", '', $generateReceptionUniqNumber->getReference());
            $number = intval($filterNumber);

            // Utilisation de number_format() pour ajouter des zéros à gauche
            $uniqueNumber = 'SAL/RET/REC/' . str_pad($number + 1, 5, '0', STR_PAD_LEFT);
        }

        $reception = new Reception();

        $reception->setSaleReturnInvoice($saleReturnInvoice);
        $reception->setContact($saleReturnInvoice->getCustomer()->getContact());
        // shipping address
        // operation type
        // original document
        $reception->setReference($uniqueNumber);
        $reception->setOtherReference($saleReturnInvoice->getInvoiceNumber());
        // serial number
        $reception->setReceiveAt(new \DateTimeImmutable());
        $reception->setDescription('sale return invoice reception validate');
        $reception->setIsValidate(true);
        $reception->setValidateAt(new \DateTimeImmutable());
        $reception->setValidateBy($this->getUser());
        $reception->setStatus('reception');

        $reception->setIsEnable(true);
        $reception->setCreatedAt(new \DateTimeImmutable());
        $reception->setYear($this->getUser()->getCurrentYear());
        $reception->setUser($this->getUser());
        $reception->setBranch($this->getUser()->getBranch());
        $reception->setInstitution($this->getUser()->getInstitution());

        $entityManager->persist($reception);

        $saleReturnInvoiceItems = $saleReturnInvoiceItemRepository->findBy(['saleReturnInvoice' => $saleReturnInvoice]);
        if ($saleReturnInvoiceItems)
        {
            foreach ($saleReturnInvoiceItems as $saleReturnInvoiceItem)
            {
                $receptionItem = new ReceptionItem();

                $receptionItem->setReception($reception);
                $receptionItem->setItem($saleReturnInvoiceItem->getItem());
                $receptionItem->setQuantity($saleReturnInvoiceItem->getQuantity());

                $receptionItem->setIsEnable(true);
                $receptionItem->setCreatedAt(new \DateTimeImmutable());
                $receptionItem->setYear($this->getUser()->getCurrentYear());
                $receptionItem->setUser($this->getUser());
                $receptionItem->setInstitution($this->getUser()->getInstitution());

                $this->manager->persist($receptionItem);

                // find sale return invoice item stock
                $saleReturnInvoiceItemStocks = $saleReturnInvoiceItemStockRepository->findBy(['saleReturnInvoiceItem' => $saleReturnInvoiceItem]);
                if($saleReturnInvoiceItemStocks)
                {
                    foreach ($saleReturnInvoiceItemStocks as $saleReturnInvoiceItemStock)
                    {
                        // create reception item stock
                        $receptionItemStock = new ReceptionItemStock();

                        $receptionItemStock->setSaleReturnInvoiceItem($saleReturnInvoiceItem);
                        $receptionItemStock->setReceptionItem($receptionItem);
                        $receptionItemStock->setStock($saleReturnInvoiceItemStock->getStock());
                        $receptionItemStock->setQuantity($saleReturnInvoiceItemStock->getQuantity());

                        $receptionItemStock->setCreatedAt(new \DateTimeImmutable());
                        $receptionItemStock->setUser($this->getUser());
                        $receptionItemStock->setYear($this->getUser()->getCurrentYear());
                        $receptionItemStock->setBranch($this->getUser()->getBranch());
                        $receptionItemStock->setInstitution($this->getUser()->getInstitution());

                        $this->manager->persist($receptionItemStock);
                        // create reception item stock end
                    }
                }
                // find sale invoice item stock end
            }
        }

        // other invoice status update
        $saleReturnInvoice->setOtherStatus('reception');

        $this->manager->flush();

        return $this->json(['hydra:member' => $reception]);
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
