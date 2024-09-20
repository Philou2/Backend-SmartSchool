<?php

namespace App\Controller\School\Schooling\Discipline;

use App\Entity\School\Schooling\Discipline\ConsignmentHour;
use App\Repository\School\Schooling\Discipline\ConsignmentHourRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
final class GetConsignmentHoursController extends AbstractController
{
    public function __invoke(Request $request, ConsignmentHourRepository $consignmentHoursRepository): JsonResponse
    {
        $consignmentHours = $consignmentHoursRepository->findAll();

        $myConsignmentHours = [];
        foreach ($consignmentHours as $consignmentHour){

            $myConsignmentHours [] = [
                '@id' => "/api/get/consignment-hours/".$consignmentHour->getId(),
                '@type' => 'ConsignmentHour',
                'id' => $consignmentHour->getId(),
                'startDate' => $consignmentHour->getStartDate() ? $consignmentHour->getStartDate()->format('Y-m-d') : '',
                'startTime' => $consignmentHour->getStartTime() ? $consignmentHour->getStartTime()->format('H:i') : '',
                'endTime' => $consignmentHour->getEndTime() ? $consignmentHour->getEndTime()->format('H:i') : '',
                'school' => $consignmentHour->getSchool() ? $consignmentHour->getSchool()->getName() : '',
                'class' => $consignmentHour->getSchoolClass() ? $consignmentHour->getSchoolClass()->getCode() : '',
                'sequence' => $consignmentHour->getSequence() ? $consignmentHour->getSequence()->getCode() : '',
                'motif' => $consignmentHour->getMotif() ? $consignmentHour->getMotif()->getName() : '',
                'observations' => $consignmentHour->getObservations(),
                'studentRegistration' => $this->serializeStudentRegistration($consignmentHour),
            ];
        }

        return $this->json(['hydra:member' => $myConsignmentHours]);

    }

    public function serializeStudentRegistration(ConsignmentHour $consignmentHour): array
    {
        $studentRegistrations = $consignmentHour->getStudentRegistration();
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
