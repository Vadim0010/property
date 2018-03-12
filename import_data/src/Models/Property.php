<?php

class Property
{
    private $wpdb;

    private $prefix;

    public function __construct()
    {
        global $wpdb;

        $this->wpdb = $wpdb;
        $this->prefix = $wpdb->prefix;
    }

    public function findProperty($propertyFileds)
    {
        $query = $this->wpdb->prepare(
            "SELECT id FROM `{$this->prefix}posts` as p INNER JOIN `{$this->prefix}postmeta` as pm ON p.id = pm.post_id AND pm.meta_key = %s AND pm.meta_value = %s",
            'property_code',
            (string) $propertyFileds['propertyref']
        );

        return $this->wpdb->get_var($query);
    }

    public function getMediaGalerry($post_id)
    {
        $query = $this->wpdb->prepare(
            "SELECT id, post_title FROM `{$this->prefix}posts` as p WHERE p.post_type = 'attachment' AND p.post_parent = %d",
            $post_id
        );

        return $this->wpdb->get_results($query);
    }

    public function getFirstMediaImage($post_id)
    {
        $query = $this->wpdb->prepare(
            "SELECT id FROM `{$this->prefix}posts` as p WHERE p.post_type = 'attachment' AND p.post_parent = %d LIMIT 1",
            $post_id
        );

        return $this->wpdb->get_var($query);
    }
}