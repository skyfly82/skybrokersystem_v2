<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\CmsPage;
use App\Repository\CmsPageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/cms', name: 'api_cms_')]
class CmsController extends AbstractController
{
    public function __construct(private readonly CmsPageRepository $pages) {}

    #[Route('/pages', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $status = $request->query->get('status');
        $qb = $this->pages->createQueryBuilder('p')->orderBy('p.createdAt', 'DESC');
        if ($status) {
            $qb->andWhere('p.status = :status')->setParameter('status', $status);
        }
        $items = $qb->setMaxResults((int)($request->query->get('limit', 100)))->getQuery()->getResult();
        $out = array_map(static function (CmsPage $p) {
            return [
                'id' => $p->getId(),
                'title' => $p->getTitle(),
                'slug' => $p->getSlug(),
                'status' => $p->getStatus(),
                'published_at' => $p->getPublishedAt()?->format(DATE_ATOM),
                'updated_at' => $p->getUpdatedAt()?->format(DATE_ATOM),
            ];
        }, $items);
        return $this->json($out);
    }

    #[Route('/pages', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $title = trim((string)($data['title'] ?? ''));
        $slug = trim((string)($data['slug'] ?? ''));
        $content = (string)($data['content'] ?? '');
        $status = (string)($data['status'] ?? 'draft');
        if (!$title || !$slug) {
            return $this->json(['message' => 'title and slug are required'], 400);
        }
        $page = (new CmsPage())
            ->setTitle($title)
            ->setSlug($slug)
            ->setContent($content)
            ->setStatus($status)
            ->setMetaTitle($data['meta_title'] ?? null)
            ->setMetaDescription($data['meta_description'] ?? null)
            ->setExcerpt($data['excerpt'] ?? null);
        $this->pages->save($page, true);
        return $this->json(['id' => $page->getId()], 201);
    }

    #[Route('/pages/{id}', name: 'update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $page = $this->pages->find($id);
        if (!$page) {
            return $this->json(['message' => 'Not found'], 404);
        }
        $data = json_decode($request->getContent(), true) ?? [];
        if (isset($data['title'])) { $page->setTitle((string)$data['title']); }
        if (isset($data['slug'])) { $page->setSlug((string)$data['slug']); }
        if (isset($data['content'])) { $page->setContent((string)$data['content']); }
        if (isset($data['excerpt'])) { $page->setExcerpt($data['excerpt']); }
        if (isset($data['status'])) { $page->setStatus((string)$data['status']); }
        if (array_key_exists('meta_title', $data)) { $page->setMetaTitle($data['meta_title']); }
        if (array_key_exists('meta_description', $data)) { $page->setMetaDescription($data['meta_description']); }
        $page->touch();
        $this->pages->save($page, true);
        return $this->json(['ok' => true]);
    }

    #[Route('/pages/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $page = $this->pages->find($id);
        if (!$page) {
            return $this->json(['message' => 'Not found'], 404);
        }
        $em = $this->pages->getEntityManager();
        $em->remove($page);
        $em->flush();
        return $this->json(['ok' => true], 204);
    }

    #[Route('/page/{slug}', name: 'by_slug', methods: ['GET'])]
    public function bySlug(string $slug, Request $request): Response
    {
        $page = $this->pages->findPublishedBySlug($slug);
        if (!$page) {
            return new Response('', 404);
        }
        $etag = md5((string)$page->getUpdatedAt()?->getTimestamp() . '|' . $page->getId());
        if ($request->headers->get('If-None-Match') === $etag) {
            return new Response('', 304, [
                'ETag' => $etag,
                'Cache-Control' => 'max-age=300, public',
            ]);
        }
        $resp = $this->json([
            'title' => $page->getTitle(),
            'slug' => $page->getSlug(),
            'content' => $page->getContent(),
            'meta_title' => $page->getMetaTitle(),
            'meta_description' => $page->getMetaDescription(),
            'published_at' => $page->getPublishedAt()?->format(DATE_ATOM),
        ]);
        $resp->setEtag($etag);
        $resp->setPublic();
        $resp->setMaxAge(300);
        return $resp;
    }
}

