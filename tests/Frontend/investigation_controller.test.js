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
    for (const type of ['cross_document_inconsistency', 'missing_evidence']) {
        guide.anomalyTypeTarget.value = type;
        guide.renderGuide();
        assert.match(guide.guideMessageTarget.textContent, /deux pièces distinctes/);
    }
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