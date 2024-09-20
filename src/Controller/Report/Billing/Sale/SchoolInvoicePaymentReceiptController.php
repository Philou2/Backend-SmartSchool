<?php

namespace App\Controller\Report\Billing\Sale;

use App\Entity\Security\User;
use App\Repository\Billing\Sale\SaleInvoiceFeeRepository;
use App\Repository\Billing\Sale\SaleSettlementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[AsController]
class SchoolInvoicePaymentReceiptController extends AbstractController
{
    public function __construct(Request $req, EntityManagerInterface $entityManager,
                                private readonly TokenStorageInterface $tokenStorage)
    {
        $this->req = $req;
        $this->entityManager = $entityManager;
    }
    public function __invoke(SaleSettlementRepository $saleSettlementRepository, SaleInvoiceFeeRepository $saleInvoiceFeeRepository, Request $request, int $id): JsonResponse
    {
        $saleSettlement = $saleSettlementRepository->find($id);

        if (!$saleSettlement) {
            return $this->json([
                'error' => 'Sale settlement not found'
            ], 404);
        }


        $settlementStudentRegistration = $saleSettlement->getStudentRegistration();

        // Fetch invoice items for the current invoice
        $saleInvoiceFees = $saleInvoiceFeeRepository->findByStudentRegistration($settlementStudentRegistration, $saleSettlement->getInvoice());

        $table = [
            '@id' => "/api/saleSettlement/" . $saleSettlement->getId(),
            '@type' => "SaleSettlement",
            'id' => $saleSettlement->getId(),
            'reference' => $saleSettlement->getReference(),
            'amountPay' => $saleSettlement->getAmountPay(),
            'amountRest' => $saleSettlement->getAmountRest(),
            'settleAt' => $saleSettlement->getSettleAt()->format('Y-m-d'),
            'year' => $saleSettlement->getYear()->getYear(),
            'customer' => $saleSettlement->getStudentRegistration()->getStudent()->getFirstName() . ' ' . $saleSettlement->getStudentRegistration()->getStudent()->getName(),
            //'firstName' => $saleSettlement->getStudentRegistration()->getStudent()->getFirstName(),
            //'name' => $saleSettlement->getStudentRegistration()->getStudent()->getName(),
            'class' => $saleSettlement->getClass()->getCode(),
            'matricule' => $saleSettlement->getStudentRegistration()->getStudent()->getMatricule(),
            'invoice' => [
                '@id' => "/api/invoice/" . $saleSettlement->getInvoice()->getId(),
                '@type' => "Invoice",
                'id' => $saleSettlement->getInvoice()->getId(),
                'invoiceItems' => array_map(function ($saleInvoiceFee) {
                    return [
                        // data from invoiceItem here
                        'id' => $saleInvoiceFee->getId(),
                        'name' => $saleInvoiceFee->getFee()->getName(),
                        'quantity' => $saleInvoiceFee->getQuantity(),
                        'pu' => $saleInvoiceFee->getPu(),
                        'amount' => $saleInvoiceFee->getAmount(),
                        'amountTtc' => $saleInvoiceFee->getAmountTtc(),
                        'balance' => $saleInvoiceFee->getBalance(),
                        'amountPaid' => $saleInvoiceFee->getAmountPaid(),
                        'discountAmount' => $saleInvoiceFee->getDiscountAmount(),
                        'deadLine' => $saleInvoiceFee->getSaleInvoice()->getDeadLine() ? $saleInvoiceFee->getSaleInvoice()->getDeadLine()->format('Y-m-d') : '',
                        'invoiceNumber' => $saleInvoiceFee->getSaleInvoice()->getInvoiceNumber(),
                        'paymentDeadLine' => $saleInvoiceFee->getFee()->getPaymentDate() ? $saleInvoiceFee->getFee()->getPaymentDate()->format('Y-m-d') : '',
                        'invoice' => [
                            '@id' => "/api/invoice/" . $saleInvoiceFee->getSaleInvoice()->getId(),
                            '@type' => "Invoice",
                            'id' => $saleInvoiceFee->getSaleInvoice()->getId(),
                            'class' => $saleInvoiceFee->getSaleInvoice()->getClass()->getCode(),
                            'school' => $saleInvoiceFee->getSaleInvoice()->getSchool()->getName(),
                            'invoiceNumber' => $saleInvoiceFee->getSaleInvoice()->getInvoiceNumber(),
                            'invoiceAt' => $saleInvoiceFee->getSaleInvoice()->getInvoiceAt()->format('Y-m-d'),
                            'amount' => $saleInvoiceFee->getSaleInvoice()->getAmount(),
                            'amountPaid' => $saleInvoiceFee->getSaleInvoice()->getAmountPaid(),
                            'balance' => $saleInvoiceFee->getSaleInvoice()->getBalance(),
                            'deadLine' => $saleInvoiceFee->getSaleInvoice()->getDeadLine(),
                            ]
                    ];
                }, $saleInvoiceFees),
            ],
        ];


        $saleSettlements= $saleSettlementRepository->findAll();
        $registration = $saleSettlement->getStudentRegistration();
        $table1 = [];

        foreach ($saleSettlements as $saleSettlement){
            if($saleSettlement->getStudentRegistration()?->getId() == $registration?->getId() ){
                $table1 [] = [
                    '@id' => "/api/saleSettlement/" . $saleSettlement->getId(),
                    '@type' => "SaleSettlement",
                    'id' => $saleSettlement->getId(),
                    'reference' => $saleSettlement->getReference(),
                    'amountPay' => $saleSettlement->getAmountPay(),
                    'amountRest' => $saleSettlement->getAmountRest(),
                    'settleAt' => $saleSettlement->getSettleAt()->format('Y-m-d'),
                    'year' => $saleSettlement->getYear()->getYear(),
                    'firstName' => $saleSettlement->getStudentRegistration()->getStudent()->getFirstName(),
                    'name' => $saleSettlement->getStudentRegistration()->getStudent()->getName(),
                    'class' => $saleSettlement->getClass()->getCode(),
                    'matricule' => $saleSettlement->getStudentRegistration()->getStudent()->getMatricule(),
                    ];
            }
        }

        return $this->json(['hydra:member' => $table, 'hydra:member1' => $table1]);
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



