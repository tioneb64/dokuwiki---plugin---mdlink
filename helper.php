<?php

use dokuwiki\Extension\Plugin;

/**
 * Mdlink : retrouve la page correspondant à un nom de note (nom de fichier Obsidian)
 */
class helper_plugin_mdlink extends Plugin
{
    /** @var array|null nom normalisé => identifiants de pages */
    protected static $byName = null;
    /** @var array|null nom de fichier média normalisé => identifiants */
    protected static $mediaByName = null;

    /**
     * Identifiant de la page de la note $name (sans extension, chemin éventuel ignoré), ou null
     *
     * @param string $name    ex. « Uniformite-Outils-DSI », « Docs\Note », « Mode opératoire »
     * @param string $current page qui contient le lien
     */
    public function find(string $name, string $current): ?string
    {
        $name = str_replace(chr(92), '/', $name);
        $name = cleanID(substr($name, (int)strrpos('/' . $name, '/')));   // dernier segment
        if ($name === '') return null;
        $ids = $this->index()[$name] ?? [];
        return $ids ? $this->closest($ids, $current) : null;
    }

    /** Identifiant du média (image, PDF, ...) de ce nom de fichier, ou null */
    public function findMedia(string $name, string $current): ?string
    {
        $name = str_replace(chr(92), '/', $name);
        $name = cleanID(substr($name, (int)strrpos('/' . $name, '/')));
        if ($name === '') return null;
        if (self::$mediaByName === null) {
            global $conf;
            $data = [];
            search($data, $conf['mediadir'], 'search_media', ['skipacl' => true]);
            $by = [];
            foreach ($data as $item) $by[noNS($item['id'])][] = $item['id'];
            foreach ($by as &$list) sort($list);
            unset($list);
            self::$mediaByName = $by;
        }
        $ids = self::$mediaByName[$name] ?? [];
        return $ids ? $this->closest($ids, $current) : null;
    }

    /** Parmi plusieurs pages de même nom : même espace, sinon plus proche espace, sinon la première */
    protected function closest(array $ids, string $current): string
    {
        if (count($ids) === 1) return $ids[0];
        $cur = explode(':', $current);
        $best = $ids[0];
        $bestScore = -1;
        foreach ($ids as $id) {
            $p = explode(':', $id);
            $score = 0;
            while ($score < count($p) - 1 && $score < count($cur) - 1 && $p[$score] === $cur[$score]) $score++;
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $id;
            }
        }
        return $best;
    }

    /** Tous les identifiants de pages, rangés par dernier segment */
    protected function index(): array
    {
        if (self::$byName !== null) return self::$byName;
        global $conf;
        $data = [];
        search($data, $conf['datadir'], 'search_allpages', ['skipacl' => true]);
        $by = [];
        foreach ($data as $item) {
            $by[noNS($item['id'])][] = $item['id'];
        }
        foreach ($by as &$list) sort($list);
        unset($list);
        return self::$byName = $by;
    }
}
