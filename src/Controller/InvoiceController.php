<?php

namespace App\Controller;

use Mpdf\Mpdf;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Invoice;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Environment as TwigEnvironment;
use Twig\Loader\ArrayLoader as TwigArrayLoader;

class InvoiceController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }
    
    #[Route('/invoice/test-html', name: 'invoice_test_html', methods: ['GET'])]
    public function testHtml(): Response
    {
        [$data] = $this->buildTestInvoiceData();
        return $this->render('invoice/invoice.html.twig', $data);
    }

    #[Route('/invoice/test-html-new', name: 'invoice_test_html_new', methods: ['GET'])]
    public function testHtmlNew(): Response
    {
        [$data] = $this->buildTestInvoiceData();
        return $this->render('invoice/invoice_new_mpdf.html.twig', $data);
    }

    #[Route('/invoice/test-pdf', name: 'invoice_test_pdf', methods: ['GET'])]
    public function testPdf(): Response
    {
        [$data, $html] = $this->buildTestInvoiceData(true);

        $mpdf = $this->createConfiguredMpdf();
        $mpdf->SetTitle('Faktura testowa');
        $mpdf->WriteHTML($html);
        $content = $mpdf->Output('', 'S');

        return new Response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="invoice-test.pdf"'
        ]);
    }

    #[Route('/invoice/test-pdf-new', name: 'invoice_test_pdf_new', methods: ['GET'])]
    public function testPdfNew(): Response
    {
        [$data] = $this->buildTestInvoiceData();

        $mpdf = $this->createConfiguredMpdf();
        $mpdf->SetTitle('Faktura testowa - Nowy szablon');
        
        // Use working template approach
        $html = $this->renderView('invoice/invoice_new_mpdf.html.twig', $data);
        $mpdf->WriteHTML($html);
        
        $content = $mpdf->Output('', 'S');

        return new Response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="invoice-test-new.pdf"'
        ]);
    }

    #[Route('/invoices', name: 'invoice_index', methods: ['GET'])]
    public function index(): Response
    {
        $invoices = $this->em->getRepository(Invoice::class)->findBy([], ['id' => 'DESC']);
        return $this->render('invoice/list.html.twig', [
            'invoices' => $invoices,
        ]);
    }

    #[Route('/invoice/{id}/html', name: 'invoice_html', methods: ['GET'])]
    public function html(int $id): Response
    {
        $invoice = $this->em->getRepository(Invoice::class)->find($id);
        if (!$invoice) {
            return new Response('Invoice not found', 404);
        }
        $tpl = $_GET['tpl'] ?? 'full';
        $template = match ($tpl) {
            'new' => 'invoice/invoice_new_mpdf.html.twig',
            default => 'invoice/invoice.html.twig',
        };
        $data = $this->mapInvoiceToTemplateData($invoice);
        // Optional: bypass Twig file cache by rendering from string
        $nocache = isset($_GET['nocache']) && $_GET['nocache'] !== '0' && $_GET['nocache'] !== 'false';
        if ($nocache) {
            $html = $this->renderTemplateFromString($template, $data);
            $response = new Response($html, 200, ['Content-Type' => 'text/html']);
        } else {
            $response = $this->render($template, $data);
        }
        $response->headers->set('X-Template', $template);
        return $response;
    }

    #[Route('/invoice/{id}/pdf', name: 'invoice_pdf', methods: ['GET'])]
    public function pdf(int $id): Response
    {
        $invoice = $this->em->getRepository(Invoice::class)->find($id);
        if (!$invoice) {
            return new Response('Invoice not found', 404);
        }

        // Engine selector & template choice
        // ?engine=browser|mpdf (default auto->browser)
        // ?tpl=new to use optimized mPDF template
        // Optional: ?zoom=1.00 (wkhtml), ?vp=1280x1800 (wkhtml viewport)
        $engine = $_GET['engine'] ?? 'auto';
        $tpl = $_GET['tpl'] ?? null;
        $template = match ($tpl) {
            'new' => 'invoice/invoice_new_mpdf.html.twig',
            default => 'invoice/invoice.html.twig',
        };
        $data = $this->mapInvoiceToTemplateData($invoice);
        $nocache = isset($_GET['nocache']) && $_GET['nocache'] !== '0' && $_GET['nocache'] !== 'false';
        $html = $nocache ? $this->renderTemplateFromString($template, $data) : $this->renderView($template, $data);

        $zoom = isset($_GET['zoom']) ? (float) $_GET['zoom'] : null;
        $viewport = isset($_GET['vp']) ? (string) $_GET['vp'] : null;

        if ($engine !== 'mpdf') {
            [$bin, $args] = $this->detectPdfBinary();
            if ($bin !== null) {
                $content = $this->renderPdfWithBrowserEngine($html, $args['type'], $bin, $zoom, $viewport);
                if ($content !== null) {
                    return new Response($content, 200, [
                        'Content-Type' => 'application/pdf',
                        'Content-Disposition' => 'inline; filename="invoice-'.$invoice->getNumber().'.pdf"',
                        'X-Template' => $template,
                        'X-Engine' => 'browser'
                    ]);
                }
            }
            if ($engine === 'browser') {
                return new Response('Browser engine (wkhtmltopdf/chrome) not available', 500);
            }
        }

        // Fallback or forced mPDF
        $mpdf = $this->createConfiguredMpdf();
        $mpdf->SetTitle(sprintf('Faktura %s', $invoice->getNumber()));
        // Feed CSS separately (mPDF best practice) to ensure styles stick
        $css = '';
        if (preg_match_all('/<style[^>]*>(.*?)<\\/style>/si', $html, $matches) && isset($matches[1])) {
            foreach ($matches[1] as $chunk) { $css .= $chunk . "\n"; }
            $html = preg_replace('/<style[^>]*>.*?<\\/style>/si', '', $html);
        }
        if ($css !== '') { $mpdf->WriteHTML($css, \Mpdf\HTMLParserMode::HEADER_CSS); }
        $mpdf->WriteHTML($html, \Mpdf\HTMLParserMode::HTML_BODY);
        $content = $mpdf->Output('', 'S');

        return new Response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="invoice-'.$invoice->getNumber().'.pdf"',
            'X-Template' => $template,
            'X-Engine' => 'mpdf'
        ]);
    }

    #[Route('/invoice/{id}/pdf-browser', name: 'invoice_pdf_browser', methods: ['GET'])]
    public function pdfBrowser(int $id): Response
    {
        $invoice = $this->em->getRepository(Invoice::class)->find($id);
        if (!$invoice) {
            return new Response('Invoice not found', 404);
        }

        $data = $this->mapInvoiceToTemplateData($invoice);
        $html = $this->renderView('invoice/invoice.html.twig', $data);

        $projectDir = $this->getParameter('kernel.project_dir');
        $publicDir = $projectDir . '/public';

        // Fix local asset URLs in @font-face and img to file:// so browser-engine binaries can access
        $html = str_replace(["url('/fonts/", 'url("/fonts/'], ["url('file://$publicDir/fonts/", "url(\"file://$publicDir/fonts/"], $html);
        $html = str_replace(['src="/','src=\"/'], ['src="file://'.$publicDir.'/', 'src=\"file://'.$publicDir.'/'], $html);

        // Write temp HTML
        $tmpHtml = tempnam(sys_get_temp_dir(), 'inv_html_') . '.html';
        file_put_contents($tmpHtml, $html);
        $tmpPdf = tempnam(sys_get_temp_dir(), 'inv_pdf_') . '.pdf';

        [$bin, $args] = $this->detectPdfBinary();
        if ($bin === null) {
            @unlink($tmpHtml);
            @unlink($tmpPdf);
            return new Response('No wkhtmltopdf or chrome headless found on server', 500);
        }

        $cmd = null;
        if ($args['type'] === 'wkhtml') {
            $cmd = sprintf('%s --enable-local-file-access --quiet --page-size A4 "%s" "%s"', escapeshellcmd($bin), $tmpHtml, $tmpPdf);
        } else { // chrome
            $cmd = sprintf('%s --headless --disable-gpu --no-sandbox --enable-local-file-accesses --print-to-pdf="%s" "%s"', escapeshellcmd($bin), $tmpPdf, $tmpHtml);
        }

        $exit = 0;
        $output = [];
        exec($cmd . ' 2>&1', $output, $exit);
        if ($exit !== 0 || !is_file($tmpPdf) || filesize($tmpPdf) === 0) {
            @unlink($tmpHtml);
            @unlink($tmpPdf);
            return new Response('PDF binary failed: ' . implode("\n", $output), 500);
        }

        $content = file_get_contents($tmpPdf);
        @unlink($tmpHtml);
        @unlink($tmpPdf);

        return new Response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="invoice-'.$invoice->getNumber().'-browser.pdf"'
        ]);
    }

    private function detectPdfBinary(): array
    {
        // 1) Explicit env overrides
        $envBrowser = getenv('PDF_BROWSER_BINARY') ?: '';
        $envChrome  = getenv('CHROME_BINARY') ?: '';
        $envWkhtml  = getenv('WKHTMLTOPDF_BINARY') ?: '';

        $try = function (?string $path): ?string {
            if (!$path) { return null; }
            if ($path[0] !== '/' && str_contains($path, DIRECTORY_SEPARATOR) === false) {
                $found = $this->findInPath($path);
                if ($found) { $path = $found; } else { return null; }
            }
            return (is_file($path) && is_executable($path)) ? $path : null;
        };

        if ($bin = $try($envBrowser)) {
            $t = str_contains($bin, 'wkhtmltopdf') ? 'wkhtml' : 'chrome';
            return [$bin, ['type' => $t]];
        }
        if ($bin = $try($envWkhtml)) {
            return [$bin, ['type' => 'wkhtml']];
        }
        if ($bin = $try($envChrome)) {
            return [$bin, ['type' => 'chrome']];
        }

        // 2) Common locations + PATH
        $wkhtmlCandidates = [
            'wkhtmltopdf', '/usr/bin/wkhtmltopdf', '/usr/local/bin/wkhtmltopdf', '/bin/wkhtmltopdf', '/snap/bin/wkhtmltopdf', '/usr/sbin/wkhtmltopdf'
        ];
        foreach ($wkhtmlCandidates as $p) {
            $candidate = $p === 'wkhtmltopdf' ? $this->findInPath($p) : $p;
            if ($candidate && is_file($candidate) && is_executable($candidate)) {
                return [$candidate, ['type' => 'wkhtml']];
            }
        }

        $chromeNames = ['google-chrome', 'google-chrome-stable', 'chromium', 'chromium-browser'];
        foreach ($chromeNames as $name) {
            $candidate = $this->findInPath($name);
            if ($candidate && is_file($candidate) && is_executable($candidate)) {
                return [$candidate, ['type' => 'chrome']];
            }
        }

        $chromeCandidates = ['/usr/bin/google-chrome', '/usr/bin/google-chrome-stable', '/usr/bin/chromium', '/usr/bin/chromium-browser'];
        foreach ($chromeCandidates as $p) {
            if (is_file($p) && is_executable($p)) {
                return [$p, ['type' => 'chrome']];
            }
        }

        return [null, ['type' => null]];
    }

    private function findInPath(string $binary): ?string
    {
        $path = getenv('PATH') ?: '';
        foreach (explode(PATH_SEPARATOR, $path) as $dir) {
            $candidate = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $binary;
            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }
        return null;
    }

    private function renderPdfWithBrowserEngine(string $html, string $type, string $bin, ?float $zoom = null, ?string $viewport = null): ?string
    {
        $projectDir = $this->getParameter('kernel.project_dir');
        $publicDir = $projectDir . '/public';

        // Adjust local asset URLs to file:// for fonts/images
        $html = str_replace(["url('/fonts/", 'url("/fonts/'], ["url('file://$publicDir/fonts/", "url(\"file://$publicDir/fonts/"], $html);
        $html = str_replace(['src="/','src=\"/'], ['src="file://'.$publicDir.'/', 'src=\"file://'.$publicDir.'/'], $html);

        $tmpHtml = tempnam(sys_get_temp_dir(), 'inv_html_') . '.html';
        $tmpPdf = tempnam(sys_get_temp_dir(), 'inv_pdf_') . '.pdf';
        file_put_contents($tmpHtml, $html);

        if ($type === 'wkhtml') {
            $args = [
                '--enable-local-file-access',
                '--quiet',
                '--page-size A4',
                '--margin-top 10mm', '--margin-right 10mm', '--margin-bottom 10mm', '--margin-left 10mm',
                '--dpi 96', '--image-dpi 300', '--image-quality 92',
                '--print-media-type', '--disable-smart-shrinking',
            ];
            if ($zoom && $zoom > 0) { $args[] = '--zoom ' . escapeshellarg((string)$zoom); }
            if ($viewport) { $args[] = '--viewport-size ' . escapeshellarg($viewport); }
            $cmd = sprintf('%s %s %s %s',
                escapeshellcmd($bin),
                implode(' ', $args),
                escapeshellarg($tmpHtml),
                escapeshellarg($tmpPdf)
            );
        } else { // chrome
            $cmd = sprintf('%s --headless --disable-gpu --no-sandbox --enable-local-file-accesses --print-to-pdf="%s" "%s"', escapeshellcmd($bin), $tmpPdf, $tmpHtml);
        }

        $exit = 0; $output = [];
        exec($cmd . ' 2>&1', $output, $exit);
        if ($exit !== 0 || !is_file($tmpPdf) || filesize($tmpPdf) === 0) {
            @unlink($tmpHtml); @unlink($tmpPdf);
            return null;
        }
        $content = file_get_contents($tmpPdf);
        @unlink($tmpHtml); @unlink($tmpPdf);
        return $content !== false ? $content : null;
    }

    private function renderTemplateFromString(string $template, array $data): string
    {
        $projectDir = $this->getParameter('kernel.project_dir');
        $filePath = $projectDir . '/templates/' . $template;
        if (!is_file($filePath)) {
            return sprintf('<h1>Template not found: %s</h1>', htmlspecialchars($template));
        }
        $source = (string) file_get_contents($filePath);
        $loader = new TwigArrayLoader(['tpl' => $source]);
        $twig = new TwigEnvironment($loader, [
            'cache' => false,
            'autoescape' => 'html',
            'strict_variables' => false,
        ]);
        return $twig->render('tpl', $data);
    }

    #[Route('/invoice/pdf-health', name: 'invoice_pdf_health', methods: ['GET'])]
    public function pdfHealth(): Response
    {
        [$bin, $args] = $this->detectPdfBinary();
        $info = [
            'detected' => $bin !== null ? $args['type'] : null,
            'binary' => $bin,
            'PATH' => getenv('PATH'),
            'env' => [
                'PDF_BROWSER_BINARY' => getenv('PDF_BROWSER_BINARY') ?: null,
                'WKHTMLTOPDF_BINARY' => getenv('WKHTMLTOPDF_BINARY') ?: null,
                'CHROME_BINARY' => getenv('CHROME_BINARY') ?: null,
            ],
        ];
        if ($bin) {
            $verCmd = $args['type'] === 'wkhtml' ? $bin . ' -V' : $bin . ' --version';
            $output = shell_exec($verCmd . ' 2>&1');
            $info['version'] = trim((string)$output);
        }
        return $this->json($info);
    }

    /**
     * @return array{0: array, 1?: string}
     */
    private function buildTestInvoiceData(bool $returnHtmlAlso = false): array
    {
        $items = [];
        $vatRate = 0.23; // 23%
        for ($i = 1; $i <= 30; $i++) {
            $qty = rand(1, 5);
            $unitBrutto = rand(1000, 25000) / 100; // 10.00 .. 250.00
            $totalBrutto = $unitBrutto * $qty;
            $items[] = [
                'lp' => $i,
                'name' => 'Usługa transportowa #' . $i,
                'code' => $i % 3 === 0 ? '49.41.13.0' : '-',
                'qty' => $qty,
                'jm' => 'szt',
                'vat' => 23,
                'unit_brutto' => $unitBrutto,
                'total_brutto' => $totalBrutto,
            ];
        }

        // Calculate summary per VAT rate (only 23% used in sample)
        $sumBrutto = array_sum(array_map(fn($r) => $r['total_brutto'], $items));
        $sumNetto = round($sumBrutto / (1 + $vatRate), 2);
        $sumVat = round($sumBrutto - $sumNetto, 2);

        $data = [
            'invoice' => [
                'number' => 'FV/' . date('Y') . '/TEST/001',
                'issue_date' => date('d.m.Y'),
                'sell_date' => date('d.m.Y'),
                'payment_due' => (new \DateTime('+7 days'))->format('d.m.Y'),
                'payment_method' => 'przelew',
            ],
            'seller' => [
                'name' => 'Sky Sp. z o.o.',
                'address' => 'ul. Przykładowa 1, 00-001 Warszawa',
                'nip' => '525-00-00-000',
                'iban' => '12 3456 7890 1234 5678 9012 3456',
                'bank' => 'Bank Przykład S.A.',
            ],
            'buyer' => [
                'name' => 'Klient Testowy Sp. z o.o.',
                'address' => 'ul. Klientów 2, 30-002 Kraków',
                'nip' => '945-00-00-000',
            ],
            'items' => $items,
            'summary' => [
                'vat_rows' => [
                    [
                        'rate' => '23%',
                        'netto' => $sumNetto,
                        'vat' => $sumVat,
                        'brutto' => $sumBrutto,
                    ],
                ],
                'total_netto' => $sumNetto,
                'total_vat' => $sumVat,
                'total_brutto' => $sumBrutto,
                'paid' => 0.00,
                'due' => $sumBrutto,
                'in_words' => $this->amountToWordsPl($sumBrutto),
            ],
        ];

        if ($returnHtmlAlso) {
            $html = $this->renderView('invoice/invoice.html.twig', $data);
            return [$data, $html];
        }
        return [$data];
    }

    private function mapInvoiceToTemplateData(Invoice $invoice): array
    {
        $items = [];
        $sumBrutto = 0.0;
        foreach ($invoice->getItems() as $it) {
            $row = [
                'lp' => $it->getLp(),
                'name' => $it->getName(),
                'code' => $it->getCode() ?? '-',
                'qty' => $it->getQty(),
                'jm' => $it->getJm(),
                'vat' => $it->getVat(),
                'unit_brutto' => $it->getUnitBrutto(),
                'total_brutto' => $it->getTotalBrutto(),
            ];
            $sumBrutto += $it->getTotalBrutto();
            $items[] = $row;
        }

        $vatRate = 0.23;
        $sumNetto = round($sumBrutto / (1 + $vatRate), 2);
        $sumVat = round($sumBrutto - $sumNetto, 2);

        $projectDir = $this->getParameter('kernel.project_dir');
        $logoPath = $projectDir . '/assets/images/blpLogoMain.png';
        
        // ZMIENIONE: przekazujemy jako file:// ścieżkę zamiast base64
        $logoSrc = is_file($logoPath) ? 'file://' . $logoPath : null;

        return [
            'logo_path' => $logoSrc, // ZMIENIONE
            'invoice' => [
                'number' => $invoice->getNumber(),
                'issue_date' => $invoice->getIssueDate()->format('d.m.Y'),
                'sell_date' => $invoice->getSellDate()->format('d.m.Y'),
                'payment_due' => $invoice->getPaymentDueDate()->format('d.m.Y'),
                'payment_method' => $invoice->getPaymentMethod(),
            ],
            'seller' => [
                'name' => $invoice->getSellerName(),
                'address' => $invoice->getSellerAddress(),
                'nip' => $invoice->getSellerNip(),
                'iban' => $invoice->getSellerIban(),
                'bank' => $invoice->getSellerBank(),
            ],
            'buyer' => [
                'name' => $invoice->getBuyerName(),
                'address' => $invoice->getBuyerAddress(),
                'nip' => $invoice->getBuyerNip(),
            ],
            'items' => $items,
            'summary' => [
                'vat_rows' => [[
                    'rate' => '23%', 'netto' => $sumNetto, 'vat' => $sumVat, 'brutto' => $sumBrutto,
                ]],
                'total_netto' => $sumNetto,
                'total_vat' => $sumVat,
                'total_brutto' => $sumBrutto,
                'paid' => $invoice->getPaidAmount(),
                'due' => $sumBrutto - $invoice->getPaidAmount(),
                'in_words' => $this->amountToWordsPl($sumBrutto),
            ],
        ];
    }

    private function amountToWordsPl(float $amount): string
    {
        // Simple pln words converter for demo (złote i grosze)
        $zl = (int) floor($amount);
        $gr = (int) round(($amount - $zl) * 100);
        return sprintf('%s złotych %s groszy', number_format($zl, 0, ',', ' '), str_pad((string)$gr, 2, '0', STR_PAD_LEFT));
    }

 private function createConfiguredMpdf(): Mpdf
{
    $defaultConfig = (new ConfigVariables())->getDefaults();
    $fontDirs = $defaultConfig['fontDir'];

    $defaultFontConfig = (new FontVariables())->getDefaults();
    $fontData = $defaultFontConfig['fontdata'];

    $projectDir = $this->getParameter('kernel.project_dir');
    // Ścieżka do czcionek w assets/fonts
    $fontsPath = $projectDir . '/assets/fonts';

    // Sprawdzenie czy katalog z czcionkami istnieje
    if (!is_dir($fontsPath)) {
        throw new \Exception("Font directory missing: $fontsPath");
    }

    // Sprawdzenie czy wymagane pliki czcionek istnieją
    $requiredFonts = [
        'BeVietnamPro-Regular.ttf',
        'BeVietnamPro-ExtraBold.ttf'
    ];
    
    foreach ($requiredFonts as $fontFile) {
        if (!is_file($fontsPath . '/' . $fontFile)) {
            throw new \Exception("Font file missing: $fontFile in $fontsPath");
        }
    }

    $mpdf = new Mpdf([
        'format' => 'A4',
        'margin_top' => 10,
        'margin_bottom' => 10,
        'margin_left' => 10,
        'margin_right' => 10,
        'tempDir' => sys_get_temp_dir(),
        'fontDir' => array_merge($fontDirs, [$fontsPath]),
        'fontdata' => $fontData + [
            'bevietnampro' => [
                'R' => 'BeVietnamPro-Regular.ttf',
                'B' => 'BeVietnamPro-ExtraBold.ttf',
            ],
        ],
        'default_font' => 'bevietnampro',
        'default_font_size' => 10,
        'dpi' => 96, // DODANE: lepsze renderowanie
        'img_dpi' => 96, // DODANE: lepsze obrazy
    ]);
    
    $mpdf->SetDisplayMode('fullwidth');
    $mpdf->shrink_tables_to_fit = 0;
    $mpdf->useKerning = true;
    $mpdf->simpleTables = true;
    $mpdf->useSubstitutions = false;
    $mpdf->tabSpaces = 4;
    $mpdf->showImageErrors = true;
    
    return $mpdf;
}
}