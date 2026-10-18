import assert from 'node:assert/strict';
import { test } from 'node:test';
import InvestigationController from '../../assets/controllers/investigation_controller.js';

function controller() {
    const attributes = new Map();
    const highlights = new Map();
    return Object.assign(Object.create(InvestigationController.prototype), {
        guideState: { hidden: false, visited: [], recorded: false },
        guideReportVisible: false,
        guideStepTarget: {},
        guideMessageTarget: {},
        guideReportTarget: {},
        guideBodyTarget: {},
        guideToggleTarget: { setAttribute: (name, value) => attributes.set(name, value) },
        anomalyTypeTarget: { value: '', classList: { toggle: (name, value) => highlights.set(name, value) } },
        notebookToggleTarget: { classList: { toggle() {} } },
        saveGuide() {},
        attributes,
        highlights,
    });
}

test('starts with reading and advances only after two distinct documents', () => {
    const guide = controller();
    guide.renderGuide();
    assert.equal(guide.guideStepTarget.textContent, 'Étape 1 / 4');
    guide.recordGuideDocument(12);
    guide.recordGuideDocument(12);
    guide.renderGuide();
    assert.equal(guide.guideStepTarget.textContent, 'Étape 1 / 4');
    guide.recordGuideDocument(13);
    guide.renderGuide();
    assert.equal(guide.guideStepTarget.textContent, 'Étape 2 / 4');
    assert.equal(guide.highlights.get('is-guide-highlighted'), true);
});

test('adapts evidence instructions to the selected anomaly type', () => {
    const guide = controller();
    guide.anomalyTypeTarget.value = 'anachronism';
    guide.renderGuide();
    assert.equal(guide.guideStepTarget.textContent, 'Étape 3 / 4');
    assert.match(guide.guideMessageTarget.textContent, /au moins un passage/);
    guide.anomalyTypeTarget.value = 'cross_document_inconsistency';
    guide.renderGuide();
    assert.match(guide.guideMessageTarget.textContent, /deux pièces distinctes/);
    guide.anomalyTypeTarget.value = 'missing_evidence';
    guide.renderGuide();
    assert.match(guide.guideMessageTarget.textContent, /au moins un passage/);
    assert.equal(guide.highlights.get('is-guide-highlighted'), false);
});

test('acknowledges recording without revealing correctness or a target count', () => {
    const guide = controller();
    guide.guideState.recorded = true;
    guide.renderGuide();
    assert.equal(guide.guideStepTarget.textContent, 'Étape 3 / 4');
    assert.match(guide.guideMessageTarget.textContent, /Repérage ajouté/);
    assert.doesNotMatch(guide.guideMessageTarget.textContent, /correct|validé/);
});

test('shows a nonblocking reminder when the report becomes visible', () => {
    const guide = controller();
    guide.guideReportVisible = true;
    guide.renderGuide();
    assert.equal(guide.guideStepTarget.textContent, 'Étape 4 / 4');
    assert.match(guide.guideMessageTarget.textContent, /même si votre examen reste incomplet/);
    guide.guideReportVisible = false;
    guide.renderGuide();
    assert.equal(guide.guideStepTarget.textContent, 'Étape 1 / 4');
});

test('hiding is reversible, persisted and removes the highlight and report hint', () => {
    const guide = controller();
    let saves = 0;
    guide.saveGuide = () => { saves += 1; };
    guide.guideState.visited = ['12', '13'];
    guide.toggleGuide();
    assert.equal(guide.guideBodyTarget.hidden, true);
    assert.equal(guide.guideReportTarget.hidden, true);
    assert.equal(guide.highlights.get('is-guide-highlighted'), false);
    assert.equal(guide.attributes.get('aria-expanded'), 'false');
    assert.equal(guide.guideToggleTarget.textContent, 'Afficher l’accompagnement');
    guide.toggleGuide();
    assert.equal(guide.guideBodyTarget.hidden, false);
    assert.equal(guide.guideReportTarget.hidden, false);
    assert.equal(guide.highlights.get('is-guide-highlighted'), true);
    assert.equal(saves, 2);
});

test('non-pilot investigations have no guide state and remain unaffected', () => {
    const guide = controller();
    delete guide.guideState;
    assert.doesNotThrow(() => guide.renderGuide());
    assert.doesNotThrow(() => guide.recordGuideDocument(12));
});

test('syncs and clears the active anomaly type for type-specific evidence markers', () => {
    const investigation = {
        element: { dataset: {} },
        anomalyTypeTarget: { value: '' },
    };

    for (const type of [
        'anachronism',
        'cross_document_inconsistency',
        'missing_evidence',
        'graphic_forgery',
        'metadata_inconsistency',
    ]) {
        investigation.anomalyTypeTarget.value = type;
        InvestigationController.prototype.syncAnomalyTypeStyle.call(investigation);
        assert.equal(investigation.element.dataset.anomalyType, type);
    }

    investigation.anomalyTypeTarget.value = '';
    InvestigationController.prototype.syncAnomalyTypeStyle.call(investigation);
    assert.equal('anomalyType' in investigation.element.dataset, false);
});

test('keeps saved selections highlighted alongside a new draft and clears removed findings', () => {
    const zone = (text) => {
        const classes = new Set();
        const attributes = new Map();
        return {
            textContent: text,
            dataset: {},
            closest: () => ({ dataset: { documentId: '7' } }),
            classList: {
                toggle(name, enabled) {
                    if (enabled) classes.add(name);
                    else classes.delete(name);
                },
                contains: (name) => classes.has(name),
            },
            setAttribute: (name, value) => attributes.set(name, value),
            attributes,
        };
    };
    const savedZone = zone('Saved passage');
    const draftZone = zone('Draft passage');
    const unusedZone = zone('Unused passage');
    const evidence = {
        querySelector(selector) {
            return selector === 'span'
                ? { textContent: 'Pièce 1' }
                : { textContent: 'Saved passage' };
        },
    };
    const savedEntry = {
        dataset: { anomalyType: 'graphic_forgery' },
        querySelectorAll: () => [evidence],
    };
    const investigation = {
        element: { querySelectorAll: () => [savedZone, draftZone, unusedZone] },
        anomalyListTarget: { querySelectorAll: () => [savedEntry] },
        documentStackTarget: {
            querySelectorAll: () => [{
                dataset: { documentId: '7' },
                querySelector: () => ({ textContent: 'Pièce 1' }),
            }],
        },
        anomalyTypeTarget: { value: 'anachronism' },
        draftEvidence: [{ documentId: 7, selectedText: 'Draft passage' }],
    };

    InvestigationController.prototype.syncSelectedZones.call(investigation);

    assert.equal(savedZone.dataset.selectedAnomalyType, 'graphic_forgery');
    assert.equal(savedZone.classList.contains('is-selected'), true);
    assert.equal(savedZone.attributes.get('aria-pressed'), 'true');
    assert.equal(draftZone.dataset.selectedAnomalyType, 'anachronism');
    assert.equal(draftZone.classList.contains('is-selected'), true);
    assert.equal(unusedZone.dataset.selectedAnomalyType, undefined);
    assert.equal(unusedZone.classList.contains('is-selected'), false);

    investigation.anomalyListTarget.querySelectorAll = () => [];
    investigation.draftEvidence = [];
    investigation.anomalyTypeTarget.value = '';
    InvestigationController.prototype.syncSelectedZones.call(investigation);

    assert.equal(savedZone.dataset.selectedAnomalyType, undefined);
    assert.equal(savedZone.classList.contains('is-selected'), false);
});

test('requires one passage for missing evidence but two distinct documents for cross-document inconsistency', () => {
    const investigation = controller();
    investigation.anomalyTypeTarget.value = 'missing_evidence';
    assert.equal(InvestigationController.prototype.minimumSelections.call(investigation), 1);
    assert.equal(InvestigationController.prototype.requiresDistinctDocuments.call(investigation), false);

    investigation.anomalyTypeTarget.value = 'cross_document_inconsistency';
    assert.equal(InvestigationController.prototype.minimumSelections.call(investigation), 2);
    assert.equal(InvestigationController.prototype.requiresDistinctDocuments.call(investigation), true);
});