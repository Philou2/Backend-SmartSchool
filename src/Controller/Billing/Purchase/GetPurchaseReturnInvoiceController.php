<?php

namespace App\Controller\Billing\Purchase;

use App\Entity\Billing\Sale\SaleReturnInvoice;
use App\Entity\Billing\Sale\SaleReturnInvoiceItem;
use App\Entity\Security\User;
use App\Repository\Billing\Sale\SaleReturnInvoiceItemRepository;
use App\Repository\Billing\Sale\SaleReturnInvoiceRepository;
use App\Repository\Billing\Sale\SaleSettlementRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[AsController]
class GetPurchaseReturnInvoiceController extends AbstractController
{
    public function __construct(private readonly TokenStorageInterface $tokenStorage,
                                private readonly SaleSettlementRepository $saleSettlementRepository,
                                )
    {
    }

    public function __invoke(SaleReturnInvoiceItemRepository $saleReturnInvoiceItemRepository,
                             SaleReturnInvoiceRepository $saleReturnInvoiceRepository,
                             Request $request): JsonResponse
    {

        $supplierSaleReturnInvoices = $saleReturnInvoiceRepository->findBy(['isCustomer' => false], ['id' => 'DESC']);

        $saleInvoices = [];
        if ($supplierSaleReturnInvoices){

        foreach ($supplierSaleReturnInvoices as $supplierSaleReturnInvoice){
            $saleInvoices[] = [
                'id' => $supplierSaleReturnInvoice->getId(),
                '@id' => '/api/get/sale-return-invoice/'.$supplierSaleReturnInvoice->getId(),
                'type' => 'SaleReturnInvoice',
                'balance' =>$supplierSaleReturnInvoice->getBalance(),

                'class' => [
                    'id' => $supplierSaleReturnInvoice->getClass() ? $supplierSaleReturnInvoice->getClass()->getId() : '',
                    'type' => 'SchoolClass',
                    'code' => $supplierSaleReturnInvoice->getClass() ? $supplierSaleReturnInvoice->getClass()->getCode() : '',
                ],

                'invoiceAt' => $supplierSaleReturnInvoice->getInvoiceAt() ? $supplierSaleReturnInvoice->getInvoiceAt()->format('Y-m-d') : '',
                'deadLine' => $supplierSaleReturnInvoice->getDeadLine() ? $supplierSaleReturnInvoice->getDeadLine()->format('Y-m-d') : '',
                'invoiceNumber' => $supplierSaleReturnInvoice->getInvoiceNumber(),
                'shippingAddress' => $supplierSaleReturnInvoice->getShippingAddress(),
                'paymentReference' => $supplierSaleReturnInvoice->getPaymentReference(),

                'amount' => $supplierSaleReturnInvoice->getAmount(),
                'amountTtc' => $supplierSaleReturnInvoice->getTtc(),
                'amountPaid' => $supplierSaleReturnInvoice->getAmountPaid(),
                'settlementStatus' => $this->settlementStatus($supplierSaleReturnInvoice),
                'supplier' => $supplierSaleReturnInvoice->getSupplier(),

                'school' => [
                    'id' => $supplierSaleReturnInvoice->getSchool() ? $supplierSaleReturnInvoice->getSchool()->getId() : '',
                    //'@id' => '/api/get/school/item/'. $supplierSaleReturnInvoice->getSchool()->getId(),
                    'type' => 'School',
                    'name' => $supplierSaleReturnInvoice->getSchool() ? $supplierSaleReturnInvoice->getSchool()->getName() : '',
                ],
                'studentRegistration' => [
                    'id' => $supplierSaleReturnInvoice->getStudentRegistration() ? $supplierSaleReturnInvoice->getStudentRegistration()->getId() : '',
                    //'@id' => '/api/get/student/registration/'. $supplierSaleReturnInvoice->getStudentRegistration()->getId(),
                    'type' => 'StudentRegistration',
                    'center' => $supplierSaleReturnInvoice->getStudentRegistration() ? $supplierSaleReturnInvoice->getStudentRegistration()->getCenter() : '',
                    'student' => [
                        'id' => $supplierSaleReturnInvoice->getStudentRegistration() ? $supplierSaleReturnInvoice->getStudentRegistration()->getStudent()->getId() : '',
                        //'@id' => '/api/students/'. $supplierSaleReturnInvoice->getStudentRegistration()->getStudent()->getId(),
                        'type' => 'Student',
                        'name' => $supplierSaleReturnInvoice->getStudentRegistration() ? $supplierSaleReturnInvoice->getStudentRegistration()->getStudent()->getName() : '',
                        'studentemail' => $supplierSaleReturnInvoice->getStudentRegistration() ? $supplierSaleReturnInvoice->getStudentRegistration()->getStudent()->getStudentemail() : '',
                        'studentphone' => $supplierSaleReturnInvoice->getStudentRegistration() ? $supplierSaleReturnInvoice->getStudentRegistration()->getStudent()->getStudentphone() : '',

                    ],
                ],
                'status' => $supplierSaleReturnInvoice->getStatus()

            ];
        }
        }

        return $this->json(['hydra:member' => $saleInvoices]);
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

    public function taxes(SaleReturnInvoiceItem $saleReturnInvoiceItem){
        $taxes = [];

        foreach ($saleReturnInvoiceItem->getTaxes() as $tax){
            $taxes[] = [
                'id' => $tax->getId(),
                'name' => $tax->getName(),
                'rate' => $tax->getRate(),
                'label' => $tax->getLabel(),
            ];
        }
        return $taxes;
    }

    public function settlementStatus(SaleReturnInvoice $supplierSaleReturnInvoice){
        $settlementStatus = '';
        $checkSettlements = $this->saleSettlementRepository->findBy(['invoice' => $supplierSaleReturnInvoice, 'isValidate' => true]);
        if ($checkSettlements){
            $totalSettlement = $this->saleSettlementRepository->sumSettlementValidatedAmountByInvoice($supplierSaleReturnInvoice)[0][1];
            //if ($supplierSaleReturnInvoice->getAmount() == $supplierSaleReturnInvoice->getAmountPaid() && $supplierSaleReturnInvoice->getBalance() == 0){
            if ($supplierSaleReturnInvoice->getTtc() == $totalSettlement){
                $settlementStatus = 'Complete Paid';
            }
            else{
                $settlementStatus = 'Partial Paid';
            }
        }
        else{
            $settlementStatus = 'Not paid';
        }

        return $settlementStatus;
    }
}
