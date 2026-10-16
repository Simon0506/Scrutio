import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        'anomalyType',
        'anomalyDescription',
        'notebook',
        'notebookToggle',
        'notebookProofCount',
        'submitAnomalyButton',
        'draftEvidenceList',
        'selectionMessage',
        'status',
        'metadataPanel',
        'anomalyList',
        'anomalyCount',
        'comparisonButton',
        'comparisonControls',
        'comparisonStage',
        'comparisonLeftSelect',
        'comparisonRightSelect',
        'comparisonLeftTitle',
        'comparisonRightTitle',
        'comparisonLeftPane',
        'comparisonRightPane',
        'documentStack',
        'guideMessage',
        'guideStep',
        'guideBody',
        'guideToggle',
        'guideReport',
        'guideReportSection',
    ];

    static values = {
        markUrl: String,
        csrfToken: String,
        guideKey: String,
    };

    initialize() {
        this.draftEvidence = [];
        this.comparisonOpen = false;
    }

    connect() {
        this.notebookMedia = window.matchMedia('(max-width: 1100px)');
        this.notebookMobile = window.innerWidth <= 1100;
        this.notebookMediaChanged = () => this.syncNotebookLayout();
        this.notebookMedia.addEventListener('change', this.notebookMediaChanged);
        this.setNotebookOpen(false, false);
        this.connectGuide();
    }

    disconnect() {
        this.notebookMedia?.removeEventListener('change', this.notebookMediaChanged);
        this.guideObserver?.disconnect();
    }

    connectGuide() {
        if (!this.hasGuideMessageTarget) {
            return;
        }

        this.guideState = { hidden: false, visited: [], recorded: false };
        this.guideReportVisible = false;
        const documentIds = new Set([...this.documentStackTarget.querySelectorAll('[data-document-id]')].map((document) => document.dataset.documentId));
        try {
            const stored = JSON.parse(localStorage.getItem(this.guideKeyValue));
            if (stored && typeof stored === 'object') {
                this.guideState.hidden = stored.hidden === true;
                this.guideState.recorded = stored.recorded === true;
                this.guideState.visited = Array.isArray(stored.visited)
                    ? [...new Set(stored.visited.filter((id) => documentIds.has(id)))]
                    : [];
            }
        } catch {
            this.guideState = { hidden: false, visited: [], recorded: false };
        }
        this.guideState.recorded ||= this.anomalyListTarget.querySelector('.anomaly-entry') !== null;
        this.renderGuide();

        this.guideObserver = new IntersectionObserver((entries) => {
            for (const entry of entries) {
                if (entry.target === this.guideReportSectionTarget) {
                    this.guideReportVisible = entry.isIntersecting;
                } else if (entry.isIntersecting && document.visibilityState === 'visible') {
                    this.recordGuideDocument(entry.target.dataset.documentId);
                }
            }
            this.renderGuide();
        }, { rootMargin: '-10% 0px -25% 0px' });
        this.observeGuideDocuments(this.documentStackTarget);
        this.guideObserver.observe(this.guideReportSectionTarget);
    }

    observeGuideDocuments(root) {
        root.querySelectorAll('[data-document-id]').forEach((document) => this.guideObserver?.observe(document));
    }

    recordGuideDocument(id) {
        if (!this.guideState || !id || this.guideState.visited.includes(String(id))) {
            return;
        }

        this.guideState.visited.push(String(id));
        this.saveGuide();
    }

    saveGuide() {
        try {
            localStorage.setItem(this.guideKeyValue, JSON.stringify(this.guideState));
        } catch {
            return;
        }
    }

    toggleGuide() {
        this.guideState.hidden = !this.guideState.hidden;
        this.saveGuide();
        this.renderGuide();
    }

    renderGuide() {
        if (!this.guideState) {
            return;
        }

        const reportMessage = 'Votre conclusion doit rester proportionnée aux preuves. Un doute ne suffit pas à établir une fraude. Vous pouvez remettre votre rapport même si votre examen reste incomplet.';
        let step = 1;
        let message = 'Commencez par lire les pièces. Repérez les dates, les personnes et la provenance des documents.';
        if (this.guideReportVisible) {
            step = 4;
            message = reportMessage;
        } else if (this.guideState.recorded) {
            step = 3;
            message = 'Repérage ajouté au rapport. Vous pouvez poursuivre l’examen du dossier. Enregistrez les constats que vous pouvez étayer, puis rendez votre conclusion dans le rapport final.';
        } else if (this.anomalyTypeTarget.value) {
            step = 3;
            message = this.requiresDistinctDocuments()
                ? 'Appuyez ce constat sur au moins deux passages issus de deux pièces distinctes. Une fois les preuves réunies, enregistrez le repérage dans le carnet.'
                : 'Sélectionnez au moins un passage probant dans une pièce. Une fois les preuves réunies, enregistrez le repérage dans le carnet.';
        } else if (this.guideState.visited.length >= 2) {
            step = 2;
            message = 'Un détail vous paraît suspect ? Dans le carnet d’analyse, choisissez un type de constat, puis sélectionnez le passage qui l’étaye.';
        }

        this.guideStepTarget.textContent = `Étape ${step} / 4`;
        this.guideMessageTarget.textContent = message;
        this.guideReportTarget.textContent = reportMessage;
        this.guideBodyTarget.hidden = this.guideState.hidden;
        this.guideReportTarget.hidden = this.guideState.hidden;
        this.guideToggleTarget.textContent = this.guideState.hidden ? 'Afficher l’accompagnement' : 'Masquer l’accompagnement';
        this.guideToggleTarget.setAttribute('aria-expanded', String(!this.guideState.hidden));
        const highlight = !this.guideState.hidden && step === 2;
        this.anomalyTypeTarget.classList.toggle('is-guide-highlighted', highlight);
        this.notebookToggleTarget.classList.toggle('is-guide-highlighted', highlight);
    }

    toggleNotebook() {
        this.setNotebookOpen(!this.notebookTarget.classList.contains('is-open'));
    }

    closeNotebook() {
        this.setNotebookOpen(false);
    }

    syncNotebookLayout() {
        const mobile = window.innerWidth <= 1100;
        if (mobile !== this.notebookMobile) {
            this.notebookMobile = mobile;
            this.setNotebookOpen(false, false);
        }
    }

    setNotebookOpen(open, manageFocus = true) {
        const mobile = window.innerWidth <= 1100;
        const shouldRestoreFocus = this.notebookTarget.contains(document.activeElement);
        this.notebookTarget.classList.toggle('is-open', mobile && open);
        this.notebookTarget.inert = mobile && !open;
        this.notebookToggleTarget.setAttribute('aria-expanded', String(mobile && open));
        if (manageFocus && mobile) {
            if (open) {
                this.anomalyTypeTarget.focus({ preventScroll: true });
            } else if (shouldRestoreFocus) {
                this.notebookToggleTarget.focus({ preventScroll: true });
            }
        }
    }

    toggleComparison() {
        this.comparisonOpen = !this.comparisonOpen;
        this.comparisonControlsTarget.hidden = !this.comparisonOpen;
        this.comparisonStageTarget.hidden = !this.comparisonOpen;
        this.documentStackTarget.hidden = this.comparisonOpen;
        this.comparisonButtonTarget.setAttribute('aria-expanded', String(this.comparisonOpen));
        this.comparisonButtonTarget.textContent = this.comparisonOpen ? 'Fermer la comparaison' : 'Ouvrir la comparaison';

        if (this.comparisonOpen) {
            this.updateComparison();
        }
    }

    updateComparison() {
        const leftId = this.comparisonLeftSelectTarget.value;
        const rightId = this.comparisonRightSelectTarget.value;

        if (leftId === rightId) {
            this.statusTarget.textContent = 'Choisissez deux pièces différentes pour les comparer.';

            return;
        }

        this.statusTarget.textContent = '';
        this.renderComparisonPane(leftId, 'left');
        this.renderComparisonPane(rightId, 'right');
        this.comparisonLeftTitleTarget.textContent = this.comparisonLeftSelectTarget.selectedOptions[0].textContent;
        this.comparisonRightTitleTarget.textContent = this.comparisonRightSelectTarget.selectedOptions[0].textContent;
        this.syncSelectedZones(this.comparisonStageTarget);
        this.updateSelectionMessage();
    }

    changeAnomalyType() {
        const selectedOption = this.anomalyTypeTarget.selectedOptions[0];
        this.anomalyDescriptionTarget.textContent = selectedOption?.dataset.description
            ?? 'Choisissez un type pour consulter sa définition et les éléments attendus.';
        const hadDraftEvidence = this.draftEvidence.length > 0;
        this.draftEvidence = [];
        this.renderDraftEvidence();
        this.syncSelectedZones();
        this.updateSubmitAnomalyState();
        this.statusTarget.textContent = hadDraftEvidence ? 'Les éléments ont été effacés car le type a changé.' : '';
        this.updateSelectionMessage();
    }

    minimumSelections() {
        return this.requiresDistinctDocuments() ? 2 : 1;
    }

    requiresDistinctDocuments() {
        return ['cross_document_inconsistency', 'missing_evidence'].includes(this.anomalyTypeTarget.value);
    }

    distinctDocumentPrompt() {
        return this.anomalyTypeTarget.value === 'missing_evidence'
            ? 'Pour établir une pièce manquante, sélectionnez sa mention et le bordereau dans deux documents distincts.'
            : 'Pour une incohérence croisée, choisissez un élément dans un autre document.';
    }

    updateSelectionMessage() {
        if (!this.anomalyTypeTarget.value) {
            this.selectionMessageTarget.textContent = '1. Choisissez le type de repérage.';

            return;
        }

        const count = this.draftEvidence.length;
        const minimum = this.minimumSelections();
        if (count < minimum) {
            this.selectionMessageTarget.textContent = this.stepTwoPrompt();

            return;
        }

        const evidenceLabel = `${count} élément${count === 1 ? '' : 's'} ajouté${count === 1 ? '' : 's'}`;
        this.selectionMessageTarget.textContent = `2. Minimum atteint (${evidenceLabel}). Cliquez à nouveau sur un élément pour le retirer, ou passez à l’étape 3.`;
    }

    stepTwoPrompt() {
        const count = this.draftEvidence.length;
        if (count === 0) {
            return this.requiresDistinctDocuments()
                ? `2. ${this.distinctDocumentPrompt()}`
                : '2. Cliquez sur un élément pour l’ajouter au repérage.';
        }

        const evidenceLabel = `${count} élément${count === 1 ? '' : 's'} déjà ajouté${count === 1 ? '' : 's'}`;
        const documentRequirement = this.requiresDistinctDocuments() ? ` dans un autre document. ${this.distinctDocumentPrompt()}` : '';

        return `2. ${evidenceLabel}. Cliquez sur un autre élément${documentRequirement} pour l’ajouter.`;
    }

    updateSubmitAnomalyState() {
        const selectedDocuments = new Set(this.draftEvidence.map((evidence) => evidence.documentId));
        const meetsDistinctDocumentRule = !this.requiresDistinctDocuments()
            || selectedDocuments.size >= this.minimumSelections();
        this.submitAnomalyButtonTarget.disabled = !this.anomalyTypeTarget.value
            || this.draftEvidence.length < this.minimumSelections()
            || !meetsDistinctDocumentRule;
        this.renderGuide();
    }

    toggleEvidenceSelection(evidence) {
        this.recordGuideDocument(evidence.documentId);
        if (!this.anomalyTypeTarget.value) {
            this.selectionMessageTarget.textContent = '1. Choisissez d’abord le type de repérage.';
            this.setNotebookOpen(true);

            return;
        }

        const existingIndex = this.draftEvidence.findIndex((item) => (
            item.documentId === evidence.documentId && item.selectedText === evidence.selectedText
        ));

        if (existingIndex >= 0) {
            this.draftEvidence.splice(existingIndex, 1);
            this.statusTarget.textContent = 'Élément retiré du repérage.';
        } else {
            if (this.draftEvidence.length >= 10) {
                this.statusTarget.textContent = 'Un repérage ne peut pas contenir plus de 10 éléments.';

                return;
            }

            if (this.requiresDistinctDocuments() && this.draftEvidence.some((item) => item.documentId === evidence.documentId)) {
                this.statusTarget.textContent = this.distinctDocumentPrompt();

                return;
            }

            this.draftEvidence.push(evidence);
            this.statusTarget.textContent = 'Élément ajouté au repérage.';
        }

        this.renderDraftEvidence();
        this.syncSelectedZones();
        this.updateSubmitAnomalyState();
        this.updateSelectionMessage();
    }

    renderDraftEvidence() {
        this.draftEvidenceListTarget.replaceChildren();
        this.notebookProofCountTarget.textContent = String(this.draftEvidence.length);
        this.notebookProofCountTarget.setAttribute('aria-label', `${this.draftEvidence.length} preuve(s) sélectionnée(s)`);

        this.draftEvidence.forEach((evidence, index) => {
            const item = document.createElement('li');
            const heading = document.createElement('strong');
            heading.textContent = `${index + 1}. ${evidence.documentTitle}`;
            const quote = document.createElement('blockquote');
            quote.textContent = evidence.selectedText;
            item.append(heading, quote);
            this.draftEvidenceListTarget.append(item);
        });
    }

    syncSelectedZones(root = this.element) {
        root.querySelectorAll('[data-selectable]').forEach((zone) => {
            const documentId = Number(zone.closest('[data-document-id]')?.dataset.documentId);
            const selectedText = zone.textContent.trim();
            const selected = this.draftEvidence.some((evidence) => (
                evidence.documentId === documentId && evidence.selectedText === selectedText
            ));
            zone.classList.toggle('is-selected', selected);
            zone.setAttribute('aria-pressed', String(selected));
        });
    }

    renderComparisonPane(documentId, side) {
        const source = this.documentStackTarget.querySelector(`[data-document-id="${CSS.escape(documentId)}"]`);
        const pane = side === 'left' ? this.comparisonLeftPaneTarget : this.comparisonRightPaneTarget;

        if (!source) {
            pane.replaceChildren();

            return;
        }

        const clone = source.cloneNode(true);
        const renamedIds = new Map();

        clone.querySelectorAll('[id]').forEach((element) => {
            const originalId = element.id;
            const renamedId = `comparison-${side}-${originalId}`;
            renamedIds.set(originalId, renamedId);
            element.id = renamedId;
        });
        clone.querySelectorAll('[aria-controls]').forEach((element) => {
            const renamedId = renamedIds.get(element.getAttribute('aria-controls'));
            if (renamedId) {
                element.setAttribute('aria-controls', renamedId);
            }
        });
        clone.classList.add('comparison-sheet');
        pane.querySelectorAll('[data-document-id]').forEach((document) => this.guideObserver?.unobserve(document));
        pane.replaceChildren(clone);
        this.syncSelectedZones(clone);
        this.observeGuideDocuments(pane);
    }

    captureSelection(event) {
        if (!event.target.closest('.document-content')) {
            return;
        }

        if (!this.anomalyTypeTarget.value) {
            this.selectionMessageTarget.textContent = '1. Choisissez d’abord le type de repérage.';
            this.setNotebookOpen(true);

            return;
        }

        const selection = window.getSelection();
        const selectedText = selection?.toString().trim() ?? '';
        if (!selectedText) {
            return;
        }

        const anchor = selection?.anchorNode;
        const focus = selection?.focusNode;
        const anchorElement = anchor?.nodeType === Node.ELEMENT_NODE ? anchor : anchor?.parentElement;
        const focusElement = focus?.nodeType === Node.ELEMENT_NODE ? focus : focus?.parentElement;
        const anchorDocument = anchorElement?.closest('[data-document-id]');
        const focusDocument = focusElement?.closest('[data-document-id]');

        if (!anchorDocument || anchorDocument !== focusDocument || selectedText.length > 1000) {
            if (selectedText.length > 1000) {
                this.statusTarget.textContent = 'Sélection trop longue. Limitez-vous au passage utile.';
            }

            return;
        }

        this.toggleEvidenceSelection({
            documentId: Number(anchorDocument.dataset.documentId),
            documentTitle: anchorDocument.querySelector('.document-content h2')?.textContent.trim() ?? 'Pièce',
            selectedText,
        });
    }

    selectZone(event) {
        const zone = event.target.closest('[data-selectable]');
        if (!zone) {
            return;
        }

        if (event.type === 'keydown') {
            event.preventDefault();
        }

        if (!this.anomalyTypeTarget.value) {
            this.selectionMessageTarget.textContent = '1. Choisissez d’abord le type de repérage.';
            this.setNotebookOpen(true);

            return;
        }

        if (window.getSelection()?.toString().trim()) {
            return;
        }

        const document = zone.closest('[data-document-id]');
        if (!document) {
            return;
        }

        this.toggleEvidenceSelection({
            documentId: Number(document.dataset.documentId),
            documentTitle: document.querySelector('.document-content h2')?.textContent.trim() ?? 'Pièce',
            selectedText: zone.textContent.trim(),
        });
    }

    clearSelectedZone() {
        this.element.querySelectorAll('[data-selectable].is-selected').forEach((zone) => {
            zone.classList.remove('is-selected');
            zone.setAttribute('aria-pressed', 'false');
        });
    }

    async submitAnomaly() {
        if (this.submitAnomalyButtonTarget.disabled) {
            return;
        }

        this.submitAnomalyButtonTarget.disabled = true;
        this.statusTarget.textContent = 'Enregistrement du repérage…';

        try {
            const response = await fetch(this.markUrlValue, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.csrfTokenValue,
                },
                body: JSON.stringify({
                    type: this.anomalyTypeTarget.value,
                    evidence: this.draftEvidence,
                }),
            });
            const result = await response.json();

            if (!response.ok) {
                throw new Error(result.error ?? 'Le repérage n’a pas pu être enregistré.');
            }

            this.anomalyListTarget.querySelector('.anomaly-empty')?.remove();
            const entry = document.createElement('li');
            entry.className = 'anomaly-entry';
            entry.dataset.anomalyId = result.id;

            const entryHeading = document.createElement('div');
            entryHeading.className = 'anomaly-entry-heading';
            const details = document.createElement('details');
            details.className = 'anomaly-entry-details';
            const summary = document.createElement('summary');
            const heading = document.createElement('strong');
            heading.textContent = this.anomalyTypeTarget.selectedOptions[0].textContent;
            summary.append(heading);
            details.append(summary);
            const removeButton = document.createElement('button');
            removeButton.type = 'button';
            removeButton.className = 'anomaly-remove';
            removeButton.setAttribute('aria-label', 'Supprimer ce repérage');
            removeButton.dataset.action = 'investigation#removeAnomaly';
            removeButton.dataset.removeUrl = result.deleteUrl;
            removeButton.dataset.csrfToken = result.deleteCsrfToken;
            removeButton.textContent = 'Supprimer';
            entryHeading.append(details, removeButton);
            entry.append(entryHeading);

            const evidenceList = document.createElement('ol');
            for (const evidence of this.draftEvidence) {
                const evidenceItem = document.createElement('li');
                const documentTitle = document.createElement('span');
                documentTitle.textContent = evidence.documentTitle;
                const quote = document.createElement('blockquote');
                quote.textContent = evidence.selectedText;
                evidenceItem.append(documentTitle, quote);
                evidenceList.append(evidenceItem);
            }
            details.append(evidenceList);

            this.anomalyListTarget.append(entry);
            if (this.guideState) {
                this.guideState.recorded = true;
                this.saveGuide();
            }
            const count = this.anomalyListTarget.querySelectorAll('.anomaly-entry').length;
            this.anomalyCountTarget.textContent = `${count} repérage${count > 1 ? 's' : ''}`;
            this.draftEvidence = [];
            this.renderDraftEvidence();
            this.clearSelectedZone();
            this.anomalyTypeTarget.value = '';
            this.updateSubmitAnomalyState();
            this.selectionMessageTarget.textContent = '1. Repérage enregistré. Choisissez le type du suivant.';
            this.statusTarget.textContent = '';
        } catch (error) {
            this.statusTarget.textContent = error instanceof Error ? error.message : 'Erreur lors de l’enregistrement.';
            this.updateSubmitAnomalyState();
        }
    }

    async removeAnomaly(event) {
        const button = event.currentTarget;
        const entry = button.closest('.anomaly-entry');
        if (!entry || button.disabled) {
            return;
        }

        button.disabled = true;
        this.statusTarget.textContent = 'Suppression du repérage…';

        try {
            const response = await fetch(button.dataset.removeUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'X-CSRF-Token': button.dataset.csrfToken},
            });

            if (!response.ok) {
                throw new Error('Le repérage n’a pas pu être supprimé.');
            }

            entry.remove();
            const count = this.anomalyListTarget.querySelectorAll('.anomaly-entry').length;
            this.anomalyCountTarget.textContent = `${count} repérage${count > 1 ? 's' : ''}`;
            if (count === 0) {
                const emptyEntry = document.createElement('li');
                emptyEntry.className = 'anomaly-empty';
                emptyEntry.textContent = 'Aucun passage consigné pour le moment.';
                this.anomalyListTarget.append(emptyEntry);
            }
            this.statusTarget.textContent = 'Repérage supprimé.';
        } catch (error) {
            button.disabled = false;
            this.statusTarget.textContent = error instanceof Error ? error.message : 'Erreur lors de la suppression.';
        }
    }

    async toggleMetadata(event) {
        const button = event.currentTarget;
        const panelId = button.getAttribute('aria-controls');
        const panel = this.metadataPanelTargets.find((target) => target.id === panelId);

        if (!panel) {
            return;
        }

        if (!panel.hidden) {
            panel.hidden = true;
            button.setAttribute('aria-expanded', 'false');
            button.textContent = 'Inspecter les métadonnées';

            return;
        }

        button.disabled = true;
        this.statusTarget.textContent = 'Lecture des métadonnées…';

        try {
            if (button.dataset.loaded !== 'true') {
                const response = await fetch(button.dataset.metadataUrl, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                });
                const result = await response.json();

                if (!response.ok) {
                    throw new Error(result.error ?? 'Les métadonnées ne sont pas accessibles.');
                }

                panel.replaceChildren();
                for (const item of result.metadata) {
                    const key = document.createElement('dt');
                    key.textContent = item.key;
                    const value = document.createElement('dd');
                    value.textContent = item.value;
                    value.dataset.selectable = '';
                    value.tabIndex = 0;
                    value.setAttribute('role', 'button');
                    value.setAttribute('aria-pressed', 'false');
                    panel.append(key, value);
                }

                if (result.metadata.length === 0) {
                    const key = document.createElement('dt');
                    key.textContent = 'Résultat';
                    const value = document.createElement('dd');
                    value.textContent = 'Aucune métadonnée disponible.';
                    panel.append(key, value);
                }

                button.dataset.loaded = 'true';
            }

            panel.hidden = false;
            button.setAttribute('aria-expanded', 'true');
            button.textContent = 'Masquer les métadonnées';
            this.syncSelectedZones(panel);
            this.statusTarget.textContent = '';
        } catch (error) {
            this.statusTarget.textContent = error instanceof Error ? error.message : 'Erreur lors de la lecture des métadonnées.';
        } finally {
            button.disabled = false;
        }
    }
}