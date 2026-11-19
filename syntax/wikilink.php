<?php

use dokuwiki\Extension\SyntaxPlugin;

/**
 * Mdlink : liens Obsidian [[Note]], [[Note|texte]], [[Note#Titre]], [[#Titre]]
 *
 * Le mode Markdown de DokuWiki n'analyse pas la syntaxe [[ ]] : ce composant la rétablit, en
 * retrouvant la page d'après le nom de la note.
 */
class syntax_plugin_mdlink_wikilink extends SyntaxPlugin
{
    public function getType()
    {
        return 'substition';
    }

    public function getPType()
    {
        return 'normal';
    }

    public function getSort()
    {
        return 299;
    }

    public function connectTo($mode)
    {
        $this->Lexer->addSpecialPattern('!?\[\[[^\]\n]+\]\](?!\])', $mode, 'plugin_mdlink_wikilink');
    }

    public function handle($match, $state, $pos, Doku_Handler $handler)
    {
        global $ID;
        $embed = $match[0] === '!';
        if ($embed) $match = substr($match, 1);
        $inner = trim(substr($match, 2, -2));
        if ($embed) return $this->embed($inner, (string)$ID);
        // liens natifs DokuWiki ([[wp>page]], [[https://...]], [[mailto:...]], [[?do=...]], [[doku.php?...]]) :
        // on laisse le noyau les classer, ce ne sont pas des noms de notes
        if (preg_match('/^\s*([a-zA-Z0-9.\-_]+>|[a-z][a-z0-9+.\-]*:\/\/|mailto:|\?|doku\.php)/i', $inner)) {
            $parts = array_pad(explode('|', $inner, 2), 2, null);
            $title = $parts[1] === null ? null : trim($parts[1]);
            if ($title !== null && preg_match('/^\{\{[^\}]+\}\}$/', $title)) {
                $title = \dokuwiki\Parsing\ParserMode\Media::parseMedia($title);
            }
            $url = trim($parts[0]);
            if (strpos($url, '?') === 0 || stripos($url, 'doku.php') === 0) {
                // adresse interne du wiki : lien externe relatif à la base DokuWiki
                $url = DOKU_URL . ltrim(stripos($url, 'doku.php') === 0 ? $url : 'doku.php' . $url, '/');
            }
            return \dokuwiki\Parsing\Helpers\Link::classify($url, $title === '' ? null : $title);
        }
        [$target, $alias] = array_pad(explode('|', $inner, 2), 2, null);
        [$name, $anchor] = array_pad(explode('#', trim($target), 2), 2, null);
        $name = trim($name);
        $anchor = $anchor === null ? '' : trim($anchor);
        $alias = $alias === null ? '' : trim($alias);

        // [[#Titre]] : section de la page courante
        if ($name === '') {
            if ($anchor === '') return false;
            return ['locallink', [sectionID($anchor, $dummy), $alias !== '' ? $alias : $anchor]];
        }

        $label = $alias;
        if ($label === '') {
            $label = trim(substr($name, (int)strrpos('/' . str_replace(chr(92), '/', $name), '/')));
            if ($anchor !== '') $label .= ' > ' . $anchor;
        }

        /** @var helper_plugin_mdlink $helper */
        $helper = plugin_load('helper', 'mdlink');
        $id = $helper->find($name, (string)$ID);
        $frag = $anchor !== '' ? '#' . sectionID($anchor, $dummy) : '';
        // note introuvable : lien vers une page à créer, dans l'espace courant
        $dest = $id !== null ? ':' . $id . $frag : cleanID($name) . $frag;
        return ['internallink', [$dest, $label]];
    }

    /** ![[image.png]], ![[image.png|300]], ![[image.png|300x200]], ![[image.png|texte alternatif]] */
    protected function embed(string $inner, string $current)
    {
        $parts = array_map('trim', explode('|', $inner));
        $name = array_shift($parts);
        $file = trim(explode('#', $name, 2)[0]);
        if ($file === '') return false;

        // une note (pas de fichier média) : son contenu est intégré
        if (!preg_match('/\.[A-Za-z0-9]{2,5}$/', $file) || preg_match('/\.md$/i', $file)) {
            $note = preg_replace('/\.md$/i', '', $file);
            $anchor = trim((string)(explode('#', $name, 2)[1] ?? ''));
            $id = plugin_load('helper', 'mdlink')->find($note, $current);
            $label = trim(substr($note, (int)strrpos('/' . str_replace(chr(92), '/', $note), '/')));
            if ($id === null) return ['internallink', [cleanID($note), $label]];
            return ['embednote', [$id, $anchor, $label]];
        }

        $width = $height = null;
        $alt = '';
        foreach ($parts as $part) {
            if (preg_match('/^(\d+)(?:x(\d+))?$/', $part, $m)) {
                $width = (int)$m[1];
                $height = isset($m[2]) ? (int)$m[2] : null;
            } elseif ($part !== '' && $alt === '') {
                $alt = $part;
            }
        }
        $id = plugin_load('helper', 'mdlink')->findMedia($file, $current);
        $src = $id !== null ? ':' . $id : cleanID($file);
        return ['internalmedia', [$src, $alt !== '' ? $alt : null, null, $width, $height, 'cache', 'details']];
    }

    /** @var string[] pages en cours d'intégration (protection contre les boucles) */
    protected static $stack = [];

    public function render($format, Doku_Renderer $renderer, $data)
    {
        if (!$data) return false;
        [$call, $args] = $data;

        if ($call === 'embednote') {
            [$id, $anchor, $label] = $args;
            if ($format === 'metadata') {
                $renderer->internallink(':' . $id, $label);      // la page intégrée est une référence
                return true;
            }
            if ($format !== 'xhtml') return false;
            $renderer->info['cache'] = false;                    // le contenu intégré peut changer
            $renderer->doc .= $this->embedHtml($id, $anchor, $label);
            return true;
        }

        if ($format === 'xhtml' || $format === 'metadata') {
            $renderer->$call(...$args);
            return true;
        }
        return false;
    }

    /** Contenu de la page $id (ou de sa section $anchor), dans un cadre cité */
    protected function embedHtml(string $id, string $anchor, string $label): string
    {
        global $ID;
        $link = '<a href="' . wl($id) . '" class="wikilink1">' . hsc($label) . '</a>';
        if ($id === $ID || in_array($id, self::$stack, true) || count(self::$stack) >= 3
            || !page_exists($id) || auth_quickaclcheck($id) < AUTH_READ) {
            return '<p class="mdlink-embed-skip">' . $link . '</p>';
        }

        self::$stack[] = $id;
        $html = (string)p_wiki_xhtml($id);
        array_pop(self::$stack);

        $html = preg_replace('#<form class="button btn_secedit".*?</form>#s', '', $html);
        $html = preg_replace('#<div class="plugin_fmeta.*?</div>\s*(?=<h|<p|<div|<ul|$)#s', '', $html, 1);
        if ($anchor !== '') {
            $html = $this->section($html, sectionID($anchor, $dummy));
            if ($html === null) return '<p class="mdlink-embed-skip">' . $link . ' &gt; ' . hsc($anchor) . '</p>';
        }
        $html = preg_replace('#\sid="[^"]*"#', '', $html);       // pas d'identifiants en double
        $html = preg_replace('#<div class="(?:toc|plugin_fmeta)[^"]*".*?</div>#s', '', $html);

        return '<div class="mdlink-embed"><div class="mdlink-embed-title">' . $link . '</div>' . $html . '</div>';
    }

    /** Extrait d'un HTML de page la section commençant au titre d'identifiant $sid */
    protected function section(string $html, string $sid): ?string
    {
        if (!preg_match('#<h([1-6])[^>]*id="' . preg_quote($sid, '#') . '"[^>]*>#', $html, $m, PREG_OFFSET_CAPTURE)) {
            return null;
        }
        $level = (int)$m[1][0];
        $start = $m[0][1];
        $rest = substr($html, $start + strlen($m[0][0]));
        if (preg_match('#<h([1-' . $level . '])[\s>]#', $rest, $n, PREG_OFFSET_CAPTURE)) {
            $rest = substr($rest, 0, $n[0][1]);
        }
        // le contenu de la section est dans un <div class="levelN">; on retire la balise fermante en trop
        return substr($html, $start, strlen($m[0][0])) . $rest;
    }
}
