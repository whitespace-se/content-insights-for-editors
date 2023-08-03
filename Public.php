<?php

if (!function_exists('broken_link_detector_array_splice_assoc')) {
    function broken_link_detector_array_splice_assoc(&$input, $offset, $length, $replacement = array())
    {
        $replacement = (array) $replacement;
        $key_indices = array_flip(array_keys($input));

        if (isset($input[$offset]) && is_string($offset)) {
            $offset = $key_indices[$offset];
        }

        if (isset($input[$length]) && is_string($length)) {
            $length = $key_indices[$length] - $offset;
        }

        $input = array_slice($input, 0, $offset, true)
                + $replacement
                + array_slice($input, $offset + $length, null, true);
    }
}
