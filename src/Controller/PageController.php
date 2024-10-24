<?php

namespace App\Controller;

use App\Entity\Category;
use App\Entity\Post;
use App\Form\ContactFormType;
use App\Form\PostFormType;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Contact;

class PageController extends AbstractController
{
    #[Route('/', name: 'index')]
    public function index(ManagerRegistry $doctrine, Request $request): Response
    {
        $repository = $doctrine->getRepository(Category::class);

        $categories = $repository->findAll();

        return $this->render('index.html.twig', ['categories' => $categories]);
    }

    #[Route('/about', name: 'about')]
    public function about(): Response{
        return $this->render('about.html.twig', []);
    }
    #[Route('/blog', name: 'blog')]
    public function blog(): Response{
        return $this->render('blog.html.twig', []);
    }
    #[Route('/blog/new', name: 'new_post')]
    public function newPost(ManagerRegistry $doctrine, Request $request, SluggerInterface $slugger): Response
    {
        $post = new Post();
        $form = $this->createForm(PostFormType::class, $post);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $image = $form->get('image')->getData();
            if ($image) {
                $originalFilename = pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME);

                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $image->guessExtension();

                try {
                    $image->move(
                        $this->getParameter('images_directory'), $newFilename
                    );
                    $filesystem = new Filesystem();
                    $filesystem->copy(
                        $this->getParameter('images_directory') . '/' . $newFilename,
                        $this->getParameter('portfolio_directory') . '/' . $newFilename, true);

                } catch (FileException $e) {
                }
                $post->setImage($newFilename);

                $post = $form->getData();
                $post->setSlug($slugger->slug($post->getTitle()));
                $post->setPostUser($this->getUser());
                $post->setNumLikes(0);
                $post->setNumComments(0);
                $entityManager = $doctrine->getManager();
                $entityManager->persist($post);
                $entityManager->flush();
                return $this->redirectToRoute('single_post', ["slug" => $post->getSlug()]);

            }
        }
        return $this->render('blog/new_post.html.twig', array(
            'form' => $form->createView()
        ));
    }

    #[Route('/blog/{page}', name: 'blog', requirements: ['page' => '\d+'])]
    public function page(ManagerRegistry $doctrine, int $page = 1): Response
    {
        $repository = $doctrine->getRepository(Post::class);
        $posts = $repository->findAll($page);

        return $this->render('blog/blog.html.twig', [
            'posts' => $posts,
        ]);
    }


    #[Route('/contact', name: 'contact')]
    public function contact(ManagerRegistry $doctrine, Request $request): Response
    {
        $contact = new Contact();
        $form = $this->createForm(ContactFormType::class, $contact);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $contacto = $form->getData();
            $entityManager = $doctrine->getManager();
            $entityManager->persist($contacto);
            $entityManager->flush();
            return $this->redirectToRoute('thankyou', []);
        }
        return $this->render('contact.html.twig', array(
            'form' => $form->createView()
        ));
    }
    #[Route('/thankyou', name: 'thankyou')]
    public function thankyou(): Response{
        return $this->render('thankyou.html.twig', []);
    }
    #[Route('/single_post/{slug}', name: 'single_post')]
    public function post(ManagerRegistry $doctrine, $slug): Response
    {
        $repositorio = $doctrine->getRepository(Post::class);
        $post = $repositorio->findOneBy(["slug"=>$slug]);
        return $this->render('blog/single_post.html.twig', [
            'post' => $post,
        ]);
    }

}
