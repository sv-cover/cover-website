<?php

namespace App\DataIter;

use App\Legacy\Database\DataIter;
use App\Legacy\Database\SearchResultInterface;
use App\Utils\SearchUtils;

class DataIterPage extends DataIter implements SearchResultInterface
{
    static public function fields()
    {
        return [
            'id',
            'committee_id',
            'titel',
            'slug',
            'content', // NL, not used anymore
            'content_en',
            'content_de', // not used anymore
            'cover_image_url',
            'last_modified'
        ];
    }

    public function get_cover_image($width=null)
    {
        return get_filemanager_url($this['cover_image_url'], $width);
    }

    public function get_cover_image_orientation()
    {
        $filemanager_root = $this->model->params->get('app.filemanager_root');
        $resize_exts = $this->model->params->get('app.filemanager_resizable_image_extensions');

        if (empty($this['cover_image_url']) || !in_array(pathinfo($this['cover_image_url'], PATHINFO_EXTENSION), $resize_exts))
            return false; // Can't determine size

        $result = file_get_contents(sprintf('%s/images/size?f=%s', $filemanager_root, urlencode($this['cover_image_url'])));
        try {
            $result = json_decode($result);
            $width = $result->width;
            $height = $result->height;
        } catch (\Exception $e) {
            return false;
        }

        if ($width == $height)
            return 'square';
        if ($width > $height)
            return 'landscape';
        else
            return 'portrait';
    }

    public function get_committee()
    {
        return $this->model->get_committee_for_iter($this);
    }

    public function get_locale_content($language = null)
    {
        if (!$language && $this->has_value('search_language'))
            $language = $this['search_language'];

        if (!$language)
            $language = i18n_get_language();

        $preferred_fields = $language == 'en'
            ? array('content_en', 'content')
            : array('content', 'content_en');

        foreach ($preferred_fields as $field)
            if ($this->has_field($field) && $this->get($field) != '')
                return $this->get($field);

        return null;
    }

    public function get_title($language = null)
    {
        $content = $this->get_locale_content($language);

        return isset($content) && preg_match('/\[h1\](.+?)\[\/h1\]\s*/ism', $content ?? '', $match)
            ? $match[1]
            : $this->get('titel');
    }

    public function get_summary($language = null)
    {
        return $this->model->get_summary_for_iter($this);
    }

    public function get_search_relevance(): float
    {
        return SearchUtils::normalizeRank($this->data['search_relevance']);
    }

    public function get_search_type(): string
    {
        return 'page';
    }
}
