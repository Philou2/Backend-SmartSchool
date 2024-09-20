<?php

namespace App\Controller\School\Schooling\Discipline;

use App\Entity\School\Schooling\Discipline\ConsignmentDay;
use App\Repository\School\Schooling\Discipline\ConsignmentDayRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
final class GetConsignmentDaysController extends AbstractController
{

    public function __invoke(Request $request, ConsignmentDayRepository $consignmentDaysRepository): JsonResponse
    {
        $consignmentDays = $consignmentDaysRepository->findAll();

        $myConsignmentDays = [];
        foreach ($consignmentDays as $consignmentDay){

            $myConsignmentDays [] = [
                '@id' => "/api/get/consignmentDays/".$consignmentDay->getId(),
                '@type' => 'ConsignmentDay',
                'id' => $consignmentDay->getId(),
                'startDate' => $consignmentDay->getStartDate() ? $consignmentDay->getStartDate()->format('Y-m-d') : '',
                'endDate' => $consignmentDay->getEndDate() ? $consignmentDay->getEndDate()->format('Y-m-d') : '',
                'school' => $consignmentDay->getSchool() ? $consignmentDay->getSchool()->getName() : '',
                'class' => $consignmentDay->getSchoolClass() ? $consignmentDay->getSchoolClass()->getCode() : '',
                'sequence' => $consignmentDay->getSequence() ? $consignmentDay->getSequence()->getCode() : '',
                'motif' => $consignmentDay->getMotif() ? $consignmentDay->getMotif()->getName() : '',
                'observations' => $consignmentDay->getObservations(),
                'studentRegistration' => $this->serializeStudentRegistration($consignmentDay),
            ];
        }

        return $this->json(['hydra:member' => $myConsignmentDays]);

    }

    public function serializeStudentRegistration(ConsignmentDay $consignmentDay): array
    {
        $studentRegistrations = $consignmentDay->getStudentRegistration();
        $myStudentRegistrations = [];
        foreach ($studentRegistrations as $studentRegistration){
            $myStudentRegistrations[] = [
                '@type' => 'StudentRegistration',
                '@id' => '/api/get/student-registration/'.$studentRegistration->getId(),
                'id' => $studentRegistration->getId(),
                'name' => $studentRegistration->getStudent()->getName(),
                'firstName' => $studentRegistration->getStudent()->getFirstName(),
            ];
        }
        return $myStudentRegistrations;
    }

}
