"""Prevent HTML/SVG layouts drifting back into browser controllers."""
from pathlib import Path
import re
import unittest

ROOT = Path(__file__).resolve().parents[1]

class ViewArchitecture(unittest.TestCase):
    def test_browser_controllers_do_not_construct_markup(self):
        patterns = [r'\bcreateElement(?:NS)?\s*\(', r'\b(?:innerHTML|outerHTML)\s*=',
                    r'\binsertAdjacentHTML\s*\(', r'\bel\s*\(', r'\belement\s*\(']
        violations = []
        for path in (ROOT / 'public/assets/js').glob('*.js'):
            for number, line in enumerate(path.read_text().splitlines(), 1):
                if any(re.search(pattern, line) for pattern in patterns):
                    violations.append(f'{path.name}:{number}: {line.strip()}')
        self.assertEqual(violations, [], '\n' + '\n'.join(violations))

    def test_page_and_component_views_exist(self):
        for relative in ['pages/account/login.php', 'pages/dashboard/index.php',
                         'pages/softcopy/index.php', 'pages/hardcopy/index.php',
                         'pages/requests/index.php', 'pages/workflows/index.php',
                         'components/registry.php', 'components/forms/fields.php',
                         'components/records/table.php', 'components/dialogs/modal.php',
                         'components/workspace/icons.php', 'components/records/list.php']:
            with self.subTest(path=relative):
                self.assertTrue((ROOT/'application/views'/relative).is_file(), relative)

    def test_views_do_not_contain_inline_script_or_event_handlers(self):
        for path in (ROOT / 'application/views').rglob('*.php'):
            if 'errors' in path.parts:
                continue
            text = path.read_text()
            self.assertIsNone(re.search(r'<script(?![^>]*\bsrc\s*=)[^>]*>', text, re.I), str(path))
            self.assertIsNone(re.search(r'\son(?:click|submit|change|load|error)\s*=', text, re.I), str(path))

if __name__ == '__main__':
    unittest.main()
