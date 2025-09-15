<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\CmsPage;
use App\Repository\CmsPageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/cms')]
#[IsGranted('ROLE_SYSTEM_USER')]
class CmsPageController extends AbstractController
{
    public function __construct(
        private readonly CmsPageRepository $cmsPageRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly SluggerInterface $slugger
    ) {}

    #[Route('', name: 'admin_cms', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $search = $request->query->get('search', '');
        $status = $request->query->get('status', '');
        $type = $request->query->get('type', '');
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 25;

        $filters = [
            'search' => $search,
            'status' => $status,
            'type' => $type,
            'page' => $page,
            'limit' => $limit,
        ];

        $pages = $this->cmsPageRepository->findWithFilters($filters);
        $totalPages = $this->cmsPageRepository->countWithFilters($filters);

        return $this->render('admin/cms/index.html.twig', [
            'pages' => $pages,
            'total_pages' => $totalPages,
            'current_page' => $page,
            'total_pages_count' => ceil($totalPages / $limit),
            'filters' => $filters,
            'statistics' => $this->getCmsStatistics(),
        ]);
    }

    #[Route('/api', name: 'admin_cms_api', methods: ['GET'])]
    public function getPagesApi(Request $request): JsonResponse
    {
        $search = $request->query->get('search', '');
        $status = $request->query->get('status', '');
        $type = $request->query->get('type', '');
        $page = max(1, $request->query->getInt('page', 1));
        $limit = $request->query->getInt('limit', 25);

        $filters = [
            'search' => $search,
            'status' => $status,
            'type' => $type,
            'page' => $page,
            'limit' => $limit,
        ];

        $pages = $this->cmsPageRepository->findWithFilters($filters);
        $totalPages = $this->cmsPageRepository->countWithFilters($filters);

        $pagesData = [];
        foreach ($pages as $page) {
            $pagesData[] = [
                'id' => $page->getId(),
                'title' => $page->getTitle(),
                'slug' => $page->getSlug(),
                'type' => $page->getType(),
                'status' => $page->getStatus(),
                'is_published' => $page->isPublished(),
                'author' => $page->getAuthor(),
                'created_at' => $page->getCreatedAt()?->format('Y-m-d H:i:s'),
                'updated_at' => $page->getUpdatedAt()?->format('Y-m-d H:i:s'),
                'published_at' => $page->getPublishedAt()?->format('Y-m-d H:i:s'),
            ];
        }

        return $this->json([
            'pages' => $pagesData,
            'total' => $totalPages,
            'page' => $page,
            'total_pages' => ceil($totalPages / $limit),
        ]);
    }

    #[Route('/create', name: 'admin_cms_create', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function create(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            return $this->createPage($request);
        }

        return $this->render('admin/cms/create.html.twig', [
            'page_types' => $this->getPageTypes(),
        ]);
    }

    #[Route('/{id}', name: 'admin_cms_show', methods: ['GET'])]
    public function show(CmsPage $page): Response
    {
        return $this->render('admin/cms/show.html.twig', [
            'page' => $page,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_cms_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(CmsPage $page, Request $request): Response
    {
        if ($request->isMethod('POST')) {
            return $this->updatePage($page, $request);
        }

        return $this->render('admin/cms/edit.html.twig', [
            'page' => $page,
            'page_types' => $this->getPageTypes(),
        ]);
    }

    #[Route('/{id}/publish', name: 'admin_cms_publish', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function publish(CmsPage $page): JsonResponse
    {
        if ($page->isPublished()) {
            return $this->json(['error' => 'Page is already published'], 400);
        }

        $page->setStatus('published');
        $page->setPublishedAt(new \DateTime());
        $page->setUpdatedAt(new \DateTime());

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Page published successfully',
            'status' => $page->getStatus(),
        ]);
    }

    #[Route('/{id}/unpublish', name: 'admin_cms_unpublish', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function unpublish(CmsPage $page): JsonResponse
    {
        if (!$page->isPublished()) {
            return $this->json(['error' => 'Page is not published'], 400);
        }

        $page->setStatus('draft');
        $page->setUpdatedAt(new \DateTime());

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Page unpublished successfully',
            'status' => $page->getStatus(),
        ]);
    }

    #[Route('/{id}/duplicate', name: 'admin_cms_duplicate', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function duplicate(CmsPage $page): JsonResponse
    {
        $duplicatedPage = new CmsPage();
        $duplicatedPage->setTitle($page->getTitle() . ' (Copy)');
        $duplicatedPage->setSlug($this->generateUniqueSlug($page->getSlug() . '-copy'));
        $duplicatedPage->setContent($page->getContent());
        $duplicatedPage->setType($page->getType());
        $duplicatedPage->setStatus('draft');
        $duplicatedPage->setAuthor($this->getUser()->getFullName());
        $duplicatedPage->setMetaTitle($page->getMetaTitle());
        $duplicatedPage->setMetaDescription($page->getMetaDescription());
        $duplicatedPage->setMetaKeywords($page->getMetaKeywords());

        $this->entityManager->persist($duplicatedPage);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Page duplicated successfully',
            'page_id' => $duplicatedPage->getId(),
            'redirect_url' => $this->generateUrl('admin_cms_edit', ['id' => $duplicatedPage->getId()]),
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_cms_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(CmsPage $page): JsonResponse
    {
        $this->entityManager->remove($page);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Page deleted successfully',
        ]);
    }

    #[Route('/bulk/action', name: 'admin_cms_bulk_action', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function bulkAction(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $action = $data['action'] ?? null;
        $pageIds = $data['page_ids'] ?? [];

        if (!$action || empty($pageIds)) {
            return $this->json(['error' => 'Invalid action or no pages selected'], 400);
        }

        $pages = $this->cmsPageRepository->findBy(['id' => $pageIds]);

        $count = 0;
        foreach ($pages as $page) {
            switch ($action) {
                case 'publish':
                    if (!$page->isPublished()) {
                        $page->setStatus('published');
                        $page->setPublishedAt(new \DateTime());
                        $count++;
                    }
                    break;
                case 'unpublish':
                    if ($page->isPublished()) {
                        $page->setStatus('draft');
                        $count++;
                    }
                    break;
                case 'delete':
                    $this->entityManager->remove($page);
                    $count++;
                    break;
            }
            if ($action !== 'delete') {
                $page->setUpdatedAt(new \DateTime());
            }
        }

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => sprintf('%d pages processed successfully', $count),
            'processed_count' => $count,
        ]);
    }

    #[Route('/export', name: 'admin_cms_export', methods: ['GET'])]
    public function export(Request $request): Response
    {
        $format = $request->query->get('format', 'json');
        $filters = [
            'search' => $request->query->get('search', ''),
            'status' => $request->query->get('status', ''),
            'type' => $request->query->get('type', ''),
            'limit' => 10000,
        ];

        $pages = $this->cmsPageRepository->findWithFilters($filters);

        if ($format === 'json') {
            return $this->exportToJson($pages);
        }

        return $this->json(['error' => 'Unsupported export format'], 400);
    }

    #[Route('/media/upload', name: 'admin_cms_media_upload', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function uploadMedia(Request $request): JsonResponse
    {
        $uploadedFile = $request->files->get('file');

        if (!$uploadedFile) {
            return $this->json(['error' => 'No file uploaded'], 400);
        }

        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($uploadedFile->getMimeType(), $allowedTypes)) {
            return $this->json(['error' => 'Invalid file type'], 400);
        }

        $maxSize = 5 * 1024 * 1024; // 5MB
        if ($uploadedFile->getSize() > $maxSize) {
            return $this->json(['error' => 'File too large'], 400);
        }

        $uploadsDirectory = $this->getParameter('kernel.project_dir') . '/public/uploads/cms';
        if (!is_dir($uploadsDirectory)) {
            mkdir($uploadsDirectory, 0755, true);
        }

        $filename = uniqid() . '.' . $uploadedFile->guessExtension();
        $uploadedFile->move($uploadsDirectory, $filename);

        return $this->json([
            'success' => true,
            'filename' => $filename,
            'url' => '/uploads/cms/' . $filename,
            'size' => $uploadedFile->getSize(),
        ]);
    }

    private function createPage(Request $request): Response
    {
        $data = $request->request->all();

        $page = new CmsPage();
        $page->setTitle($data['title'] ?? '');
        $page->setSlug($this->generateUniqueSlug($data['slug'] ?? $this->slugger->slug($data['title'] ?? '')));
        $page->setContent($data['content'] ?? '');
        $page->setType($data['type'] ?? 'page');
        $page->setStatus($data['status'] ?? 'draft');
        $page->setAuthor($this->getUser()->getFullName());
        $page->setMetaTitle($data['meta_title'] ?? '');
        $page->setMetaDescription($data['meta_description'] ?? '');
        $page->setMetaKeywords($data['meta_keywords'] ?? '');

        if ($page->isPublished()) {
            $page->setPublishedAt(new \DateTime());
        }

        $this->entityManager->persist($page);
        $this->entityManager->flush();

        $this->addFlash('success', 'Page created successfully');

        return $this->redirectToRoute('admin_cms_show', ['id' => $page->getId()]);
    }

    private function updatePage(CmsPage $page, Request $request): Response
    {
        $data = $request->request->all();

        $page->setTitle($data['title'] ?? $page->getTitle());
        $page->setSlug($data['slug'] ?? $page->getSlug());
        $page->setContent($data['content'] ?? $page->getContent());
        $page->setType($data['type'] ?? $page->getType());
        $page->setStatus($data['status'] ?? $page->getStatus());
        $page->setMetaTitle($data['meta_title'] ?? $page->getMetaTitle());
        $page->setMetaDescription($data['meta_description'] ?? $page->getMetaDescription());
        $page->setMetaKeywords($data['meta_keywords'] ?? $page->getMetaKeywords());
        $page->setUpdatedAt(new \DateTime());

        if ($page->isPublished() && !$page->getPublishedAt()) {
            $page->setPublishedAt(new \DateTime());
        }

        $this->entityManager->flush();

        $this->addFlash('success', 'Page updated successfully');

        return $this->redirectToRoute('admin_cms_show', ['id' => $page->getId()]);
    }

    private function generateUniqueSlug(string $baseSlug): string
    {
        $slug = $this->slugger->slug($baseSlug)->lower();
        $counter = 1;

        while ($this->cmsPageRepository->findBySlug($slug)) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    private function getCmsStatistics(): array
    {
        return [
            'total' => $this->cmsPageRepository->count([]),
            'published' => $this->cmsPageRepository->count(['status' => 'published']),
            'draft' => $this->cmsPageRepository->count(['status' => 'draft']),
            'pages' => $this->cmsPageRepository->count(['type' => 'page']),
            'blog_posts' => $this->cmsPageRepository->count(['type' => 'blog']),
            'help_articles' => $this->cmsPageRepository->count(['type' => 'help']),
        ];
    }

    private function getPageTypes(): array
    {
        return [
            'page' => 'Static Page',
            'blog' => 'Blog Post',
            'help' => 'Help Article',
            'legal' => 'Legal Document',
            'faq' => 'FAQ',
        ];
    }

    private function exportToJson(array $pages): Response
    {
        $data = [];
        foreach ($pages as $page) {
            $data[] = [
                'id' => $page->getId(),
                'title' => $page->getTitle(),
                'slug' => $page->getSlug(),
                'content' => $page->getContent(),
                'type' => $page->getType(),
                'status' => $page->getStatus(),
                'author' => $page->getAuthor(),
                'meta_title' => $page->getMetaTitle(),
                'meta_description' => $page->getMetaDescription(),
                'meta_keywords' => $page->getMetaKeywords(),
                'created_at' => $page->getCreatedAt()?->format('Y-m-d H:i:s'),
                'updated_at' => $page->getUpdatedAt()?->format('Y-m-d H:i:s'),
                'published_at' => $page->getPublishedAt()?->format('Y-m-d H:i:s'),
            ];
        }

        $response = new Response(json_encode($data, JSON_PRETTY_PRINT));
        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('Content-Disposition', 'attachment; filename="cms_pages_export.json"');

        return $response;
    }
}