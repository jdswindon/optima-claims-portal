<?php 

$context = Timber::context();
$timber_post = new Timber\Post();
$context['post'] = $timber_post;

$context['categories'] = get_categories( array(
    'orderby' => 'name',
    'order'   => 'ASC'
) );

$context['prev_post'] = Timber::get_post(get_previous_post());
$context['next_post'] = Timber::get_post(get_next_post());

Timber::render( [ 'single-' . $timber_post->post_type . '.twig', 'single.twig' ], $context );

if (is_singular('post')) { ?>
  <script type="text/javascript">
    document.querySelectorAll('.current_page_parent').forEach(function(parent) {
      parent.classList.add('current-menu-item');
    });
  </script>
<?php }