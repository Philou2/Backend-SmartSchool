<?php

namespace App\Controller\School\Schooling\Discipline;

use App\Entity\School\Schooling\Discipline\AbsencePermit;
use App\Repository\School\Schooling\Discipline\AbsencePermitRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
final class GetAbsencePermitController extends AbstractController
{

    public function __invoke(Request $request, AbsencePermitRepository $absencePermitRepository): JsonResponse
    {
        $absencePermits = $absencePermitRepository->findAll();

        $myAbsencePermits = [];
        foreach ($absencePermits as $absencePermit){

            $myAbsencePermits [] = [
                '@id' => "/api/get/absencePermit/".$absencePermit->getId(),
                '@type' => 'AbsencePermit',
                'id' => $absencePermit->getId(),
                'startDate' => $absencePermit->getStartDate() ? $absencePermit->getStartDate()->format('Y-m-d') : '',
                'endDate' => $absencePermit->getEndDate() ? $absencePermit->getEndDate()->format('Y-m-d') : '',
                'startTime' => $absencePermit->getStartTime() ? $absencePermit->getStartTime()->format('H:i') : '',
                'endTime' => $absencePermit->getEndTime() ? $absencePermit->getEndTime()->format('H:i') : '',
                'school' => $absencePermit->getSchool() ? $absencePermit->getSchool()->getName() : '',
                'class' => $absencePermit->getSchoolClass() ? $absencePermit->getSchoolClass()->getCode() : '',
                'sequence' => $absencePermit->getSequence() ? $absencePermit->getSequence()->getCode() : '',
                'motif' => $absencePermit->getReason() ? $absencePermit->getReason()->getName() : '',
                'observations' => $absencePermit->getObservations(),
                'studentRegistration' => $this->serializeStudentRegistration($absencePermit),
            ];
        }

        return $this->json(['hydra:member' => $myAbsencePermits]);

    }

    public function serializeStudentRegistration(AbsencePermit $absencePermit): array
    {
        $studentRegistrations = $absencePermit->getStudentRegistration();
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