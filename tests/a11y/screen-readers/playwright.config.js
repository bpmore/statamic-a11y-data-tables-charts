/**
 * The fixture pages read by a real screen reader: NVDA with Firefox on
 * Windows, VoiceOver with WebKit on macOS. Guidepup drives the reader and
 * records every phrase it speaks; the specs assert on those phrases and
 * write them to logs/ for a person to read.
 *
 * Runs on GitHub's runners (.github/workflows/screen-readers.yml). Running
 * it locally takes over the machine's screen reader, keyboard and speakers.
 *
 *   SCREEN_READER=nvda npx playwright test
 *   SCREEN_READER=voiceover npx playwright test
 */
import { withGuidepup } from '@guidepup/playwright';

const voiceover = process.env.SCREEN_READER === 'voiceover';

export default withGuidepup({
    testDir: '.',
    testMatch: voiceover ? 'voiceover.spec.js' : 'nvda.spec.js',
    outputDir: 'results',
    reporter: [['list']],
    reportSlowTests: null,
    timeout: 3 * 60 * 1000,
    // A screen reader on a shared runner misses a keystroke now and then.
    retries: 2,
    projects: [voiceover
        ? { name: 'webkit', use: { browserName: 'webkit' } }
        : { name: 'firefox', use: { browserName: 'firefox' } }],
});
