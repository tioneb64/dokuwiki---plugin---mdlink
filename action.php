<?php

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\Event;
use dokuwiki\Extension\EventHandler;

/**
 * Mdlink : [texte](Note.md) pointe vers la page dont l'identifiant correspond au nom de fichier.
 *
 * Le remplacement se fait sur les instructions déjà analysées : le texte de la page n'est pas
 * modifié (les décalages d'édition de section restent justes).
 */
class action_plugin_mdlink extends ActionPlugin
{
    /** @inheritdoc */
    public function register(EventHandler $controller)
    {
        $controller->register_hook('PARSER_HANDLER_DONE', 'AFTER', $this, 'rewrite');
    }

    public function rewrite(Event $event)
    {
        global $ID;
        $handler = $event->data;
        if (empty($handler->calls)) return;

        foreach ($handler->calls as &$call) {
            if ($call[0] !== 'internallink' || !isset($call[1][0]) || !is_string($call[1][0])) continue;
            $resolved = $this->resolve($call[1][0], (string)$ID);
            if ($resolved !== null) $call[1][0] = $resolved;
        }
        unset($call);
    }

    /** Identifiant (avec ancre éventuelle) de la page visée, ou null si ce n'est pas un lien .md résolu */
    protected function resolve(string $target, string $current): ?string
    {
        [$path, $anchor] = array_pad(explode('#', $target, 2), 2, null);
        $path = rawurldecode($path);
        if (!preg_match('/\.md$/i', $path)) return null;

        /** @var helper_plugin_mdlink $helper */
        $helper = plugin_load('helper', 'mdlink');
        $id = $helper->find(preg_replace('/\.md$/i', '', $path), $current);
        if ($id === null) return null;

        $frag = ($anchor !== null && $anchor !== '') ? '#' . sectionID(rawurldecode($anchor), $dummy) : '';
        return ':' . $id . $frag;
    }
}
