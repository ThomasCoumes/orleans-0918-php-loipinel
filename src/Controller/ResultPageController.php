<?php

namespace App\Controller;

use App\Entity\finance;
use App\Entity\RealEstateProperty;
use App\Repository\VariableRepository;
use App\Service\ApiAddressRequest;
use App\Service\DataPinelJson;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Knp\Snappy\Pdf;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\TaxBenefit;

/**
 * @IsGranted("ROLE_USER")
 */
class ResultPageController extends AbstractController
{
    /**
     * @param TaxBenefit $taxBenefit
     * @param Finance $finance
     * @return void
     */
    private function injectRealEstate(TaxBenefit $taxBenefit, Finance $finance) : void
    {
        $realEstate = new RealEstateProperty();
        $realEstate->setPurchasePrice($finance->getPurchasePrice());
        $realEstate->setSurfaceArea($finance->getSurfaceArea());

        $taxBenefit->setRealEstate($realEstate);
    }

    /**
     * @param SessionInterface $session
     * @param TaxBenefit $taxBenefit
     * @param DataPinelJson $dataPinelJson
     * @param ApiAddressRequest $apiAddressRequest
     * @param VariableRepository $variableRepository
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @Route("/resultat", name="result_page")
     */
    public function index(
        SessionInterface $session,
        TaxBenefit $taxBenefit,
        DataPinelJson $dataPinelJson,
        ApiAddressRequest $apiAddressRequest,
        VariableRepository $variableRepository
    ) {
        $user = $this->getUser();
        $finance = $session->get('finance');

        $this->injectRealEstate($taxBenefit, $finance);
        $taxBenefit->setRentalPeriod($finance->getDuration());
        $resultTaxBenefit = $taxBenefit->calculateTaxBenefit();

        $acquisitionDate = date_format($finance->getAcquisitionDate(), 'Y-m-d H:i:s');
        $area = $dataPinelJson->getPinelArea($acquisitionDate, $finance->getCity());

        $city = $apiAddressRequest->getCityApi($finance->getZipCode(), $finance->getCity());

        $taxBenefitByYear = $taxBenefit->taxBenefitByYear($dataPinelJson, $finance);


        return $this->render('result.html.twig', [
            'resultTaxBenefit' => $resultTaxBenefit,
            'taxBenefit' => $taxBenefit,
            'finance' => $finance,
            'user' => $user,
            'area' => $area,
            'city' => $city,
            'taxBenefitByYear' => $taxBenefitByYear,

        ]);
    }

    /**
     * @Route("/export-pdf", name="pdf_export")
     * @param Pdf $knpSnappyPdf
     * @param SessionInterface $session
     * @param TaxBenefit $taxBenefit
     * @param DataPinelJson $dataPinelJson
     * @param ApiAddressRequest $apiAddressRequest
     * @param VariableRepository $variableRepository
     * @return PdfResponse
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function pdfAction(
        Pdf $knpSnappyPdf,
        SessionInterface $session,
        TaxBenefit $taxBenefit,
        DataPinelJson $dataPinelJson,
        ApiAddressRequest $apiAddressRequest,
        VariableRepository $variableRepository
    ) {
        $finance = $session->get('finance');
        $civilStatus = $session->get('civilStatus');

        $this->injectRealEstate($taxBenefit, $finance);
        $taxBenefit->setRentalPeriod($finance->getDuration());
        $resultTaxBenefit = $taxBenefit->calculateTaxBenefit();

        $acquisitionDate = date_format($finance->getAcquisitionDate(), 'Y-m-d H:i:s');
        $area = $dataPinelJson->getPinelArea($acquisitionDate, $finance->getCity());

        $city = $apiAddressRequest->getCityApi($finance->getZipCode(), $finance->getCity());

        $taxBenefitByYear = $taxBenefit->taxBenefitByYear($dataPinelJson, $finance);

        /* creating the pdf from html page */
        $html = $this->renderView('resume.html.twig', [
            'resultTaxBenefit' => $resultTaxBenefit,
            'taxBenefit' => $taxBenefit,
            'area' => $area,
            'city' => $city,
            'taxBenefitByYear' => $taxBenefitByYear
        ]);
        $lastName = $civilStatus->getLastName();

        return new PdfResponse(
            $knpSnappyPdf->getOutputFromHtml($html, ['user-style-sheet' => ['./build/app.css',],]),
            $lastName . '_' . date("d-m-Y") . '.pdf'
        );
    }
}
