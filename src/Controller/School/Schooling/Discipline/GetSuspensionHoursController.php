<?php

namespace App\Controller\School\Schooling\Discipline;

use App\Entity\School\Schooling\Discipline\SuspensionHour;
use App\Repository\School\Schooling\Discipline\SuspensionHourRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
final class GetSuspensionHoursController extends AbstractController
{

    public function __invoke(Request $request, SuspensionHourRepository $suspensionHoursRepository): JsonResponse
    {
        $suspensionHours = $suspensionHoursRepository->findAll();

        $mySuspensionHours = [];
        foreach ($suspensionHours as $suspensionHour){

            $mySuspensionHours [] = [
                '@id' => "/api/get/suspension-hours/".$suspensionHour->getId(),
                '@type' => 'Suspension',
                'id' => $suspensionHour->getId(),
                'startDate' => $suspensionHour->getStartDate() ? $suspensionHour->getStartDate()->format('Y-m-d') : '',
                'startTime' => $suspensionHour->getStartTime() ? $suspensionHour->getStartTime()->format('H:i') : '',
                'endTime' => $suspensionHour->getEndTime() ? $suspensionHour->getEndTime()->format('H:i') : '',
                'school' => $suspensionHour->getSchool() ? $suspensionHour->getSchool()->getName() : '',
                'class' => $suspensionHour->getSchoolClass() ? $suspensionHour->getSchoolClass()->getCode() : '',
                'sequence' => $suspensionHour->getSequence() ? $suspensionHour->getSequence()->getCode() : '',
                'motif' => $suspensionHour->getMotif() ? $suspensionHour->getMotif()->getName() : '',
                'observations' => $suspensionHour->getObservations(),
                'studentRegistration' => $this->serializeStudentRegistration($suspensionHour),
            ];
        }

        return $this->json(['hydra:member' => $mySuspensionHours]);

    }

    public function serializeStudentRegistration(SuspensionHour $suspensionHour): array
    {
        $studentRegistrations = $suspensionHour->getStudentRegistration();
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
