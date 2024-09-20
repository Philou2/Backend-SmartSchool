<?php

namespace App\Controller\Billing\Sale\Settlement;

use App\Entity\Billing\Sale\SaleInvoice;
use App\Entity\Billing\Sale\SaleInvoiceItem;
use App\Entity\Security\User;
use App\Repository\Billing\Sale\SaleInvoiceItemRepository;
use App\Repository\Billing\Sale\SaleSettlementRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[AsController]
class GetSaleSettlementReferenceController extends AbstractController
{
    public function __construct(private readonly TokenStorageInterface $tokenStorage,
                                private readonly SaleSettlementRepository $saleSettlementRepository,
                                private readonly SaleInvoiceItemRepository $saleInvoiceItemRepository
    )
    {
    }

    public function __invoke(Request $request): JsonResponse
    {

        // $saleSettlements = $this->saleSettlementRepository->findBy([], ['id' => 'desc']);


        $generateSettlementUniqNumber = $this->saleSettlementRepository->findOneBy([], ['id' => 'DESC']);

        if (!$generateSettlementUniqNumber){
            $uniqueNumber = 'SAL/SET/' . str_pad( 1, 5, '0', STR_PAD_LEFT);
        }
        else{
            $filterNumber = preg_replace("/[^0-9]/", '', $generateSettlementUniqNumber->getReference());
            $number = intval($filterNumber);

            // Utilisation de number_format() pour ajouter des zéros à gauche
            $uniqueNumber = 'SAL/SET/' . str_pad($number + 1, 5, '0', STR_PAD_LEFT);
        }

//        $mySettlements = [];
//
//        foreach ($saleSettlements as $saleSettlement){
//            $mySettlements[] = [
//                'id' => $saleSettlement->getId(),
//                '@id' => '/api/get/sale-settlement/'.$saleSettlement->getId(),
//                'type' => 'SaleInvoice',
//                'invoice' => $saleSettlement->getInvoice(),
//                'saleReturnInvoice' => $saleSettlement->getSaleReturnInvoice(),
//                //'saleInvoiceItem' => $this->saleInvoiceItem($saleSettlement->getInvoice()),
//                'customer' => $saleSettlement->getCustomer(),
//                // 'studentRegistration' => $saleSettlement->getStudentRegistration(),
//                'bank' =>$saleSettlement->getBank(),
//                'bankAccount' =>$saleSettlement->getBankAccount(),
//                'cashDesk' =>$saleSettlement->getCashDesk(),
//                'paymentMethod' =>$saleSettlement->getPaymentMethod(),
//                'reference' =>$saleSettlement->getReference(),
//                'amountPay' =>$saleSettlement->getAmountPay(),
//                'settleAt' => $saleSettlement->getSettleAt() ? $saleSettlement->getSettleAt()->format('Y-m-d') : '',
//                'note' => $saleSettlement->getNote(),
//                'isValidate' => $saleSettlement->isIsValidate(),
////                'status' => $this->settlementStatus($saleSettlement->getInvoice()),
//                'paymentGateway' => $saleSettlement->getPaymentGateway(),
//
//            ];
//        }


        return $this->json(['hydra:member' => $uniqueNumber]);
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



    public function saleInvoiceItem(SaleInvoice $saleInvoice){
        $items = [];
        $saleInvoiceItems = $this->saleInvoiceItemRepository->findBy(['saleInvoice' => $saleInvoice]);
        if ($saleInvoiceItems){
            foreach ($saleInvoiceItems as $saleInvoiceItem){
                $items[] = [
                    'id' => $saleInvoiceItem->getId(),
                    '@id' => '/api/get/sale-invoice-item/'.$saleInvoiceItem->getId(),
                    'type' => 'SaleInvoiceItem',
                    'item' => $saleInvoiceItem->getItem(),
                    'name' => $saleInvoiceItem->getName(),
                    'amount' => $saleInvoiceItem->getAmount(),
                    'amountTtc' => $saleInvoiceItem->getAmountTtc(),
                    'quantity' => $saleInvoiceItem->getQuantity(),
                    'returnQuantity' => $saleInvoiceItem->getReturnQuantity(),
                    'discount' => $saleInvoiceItem->getDiscount(),
                    'discountAmount' => $saleInvoiceItem->getDiscountAmount(),
                    'amountWithTaxes' => $saleInvoiceItem->getAmountWithTaxes(),
                    'pu' => $saleInvoiceItem->getPu(),
                    'taxes' => $this->taxes($saleInvoiceItem)
                ];


            }
        }

        return $items;
    }

    public function taxes(SaleInvoiceItem $saleInvoiceItem){
        $taxes = [];

        foreach ($saleInvoiceItem->getTaxes() as $tax){
            $taxes[] = [
                'id' => $tax->getId(),
                'name' => $tax->getName(),
                'rate' => $tax->getRate(),
                'label' => $tax->getLabel(),
            ];
        }
        return $taxes;
    }
}
