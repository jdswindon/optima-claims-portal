<?php
// Force HTML Forms uploads to use direct links instead of a protected PHP handler.
// This means files are publicly accessible if someone has the URL.
add_filter( 'hf_file_upload_use_direct_links', '__return_true' );

// Prevent uploaded files from being added to the WordPress Media Library.
// This keeps the Media Library clean and only stores files in the uploads folder.
add_filter( 'hf_upload_add_to_media', '__return_false' );