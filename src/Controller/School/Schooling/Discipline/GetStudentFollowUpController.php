<?php

namespace App\Controller\School\Schooling\Discipline;

use App\Entity\School\Schooling\Discipline\StudentFollowUp;
use App\Repository\School\Schooling\Discipline\StudentFollowUpRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
final class GetStudentFollowUpController extends AbstractController
{

    public function __invoke(Request $request, StudentFollowUpRepository $studentFollowUpRepository): JsonResponse
    {
        $studentFollowUps = $studentFollowUpRepository->findAll();

        $myStudentFollowUps = [];
        foreach ($studentFollowUps as $studentFollowUp){

            $myStudentFollowUps [] = [
                '@id' => "/api/get/StudentFollowUp/".$studentFollowUp->getId(),
                '@type' => 'StudentFollowUp',
                'id' => $studentFollowUp->getId(),
                'startDate' => $studentFollowUp->getStartDate() ? $studentFollowUp->getStartDate()->format('Y-m-d') : '',
                'startTime' => $studentFollowUp->getStartTime() ? $studentFollowUp->getStartTime()->format('H:i') : '',
                'endTime' => $studentFollowUp->getEndTime() ? $studentFollowUp->getEndTime()->format('H:i') : '',
                'school' => $studentFollowUp->getSchool() ? $studentFollowUp->getSchool()->getName() : '',
                'class' => $studentFollowUp->getSchoolClass() ? $studentFollowUp->getSchoolClass()->getCode() : '',
                'evaluationPeriod' => $studentFollowUp->getEvaluationPeriod() ? $studentFollowUp->getEvaluationPeriod()->getName() : '',
                'classProgram' => $studentFollowUp->getClassProgram() ? $studentFollowUp->getClassProgram()->getNameuvc() : '',
                'teacherCourseRegistration' => $studentFollowUp->getTeacherCourseRegistration() ? $studentFollowUp->getTeacherCourseRegistration()->getTeacher()->getName() : '',
                'sequence' => $studentFollowUp->getSequence() ? $studentFollowUp->getSequence()->getCode() : '',
                'motif' => $studentFollowUp->getMotif() ? $studentFollowUp->getMotif()->getName() : '',
                'observations' => $studentFollowUp->getObservations(),
                'studentRegistration' => $this->serializeStudentRegistration($studentFollowUp),
            ];
        }

        return $this->json(['hydra:member' => $myStudentFollowUps]);

    }

    public function serializeStudentRegistration(StudentFollowUp $studentFollowUp): array
    {
        $studentRegistrations = $studentFollowUp->getStudentRegistration();
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