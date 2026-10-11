<?php
/** Journal articles support both native WordPress content and Elementor. */
defined('ABSPATH') || exit;
get_header();
?>
<main id="main" class="iaai-site-main">
<?php while (have_posts()): the_post(); ?>
    <article <?php post_class('iaai-blog-article'); ?>>
        <header>
            <p><time datetime="<?php echo esc_attr(get_the_date('c')); ?>"><?php echo esc_html(get_the_date()); ?></time></p>
            <h1><?php the_title(); ?></h1>
            <?php if (has_post_thumbnail()): ?><div class="iaai-article-image"><?php the_post_thumbnail('large'); ?></div><?php endif; ?>
        </header>
        <div class="iaai-article-content"><?php the_content(); ?></div>
        <?php wp_link_pages(['before' => '<nav aria-label="Article pages">', 'after' => '</nav>']); ?>
    </article>
<?php endwhile; ?>
</main>
<?php get_footer(); ?>
