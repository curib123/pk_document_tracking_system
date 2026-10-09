                            <?php foreach ($dt_row['buttons'] ?? [] as $button): ?>
                                <?php if ($button['type'] === 'view'): ?>
                                    <button type="button" class="btn-icon js-view" title="View" aria-label="View record" data-bs-toggle="modal" data-bs-target="#viewModal"
                                        data-display="<?= html_escape(json_encode($dt_row['display'])) ?>" data-title="<?= html_escape($button['label'] ?? 'View Details') ?>"><i class="fa-regular fa-eye"></i></button>
                                <?php elseif ($button['type'] === 'edit'): ?>
                                    <button type="button" class="btn-icon js-edit" title="Edit" aria-label="Edit record" data-bs-toggle="modal" data-bs-target="<?= html_escape($button['modal']??'#editModal') ?>" data-target="<?= html_escape($button['form']??'#editForm') ?>"
                                        data-record="<?= html_escape(json_encode($dt_row['record'])) ?>" data-title="Edit Record"><i class="fa-solid fa-pen"></i></button>
                                <?php elseif ($button['type'] === 'steps'): ?>
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal"
                                        data-bs-target="#workflowStepsModal-<?= (int)$dt_row['id'] ?>"
                                        aria-label="Step Actions for <?= html_escape($dt_row['cells']['name']??'workflow') ?>">Step Actions</button>
                                <?php elseif ($button['type'] === 'grants'): ?>
                                    <button type="button" class="btn-icon" title="Manage Access" aria-label="Manage Access"
                                        data-bs-toggle="modal"
                                        data-bs-target="#grantModal-<?= (int) $dt_row['id'] ?>">
                                        <i class="fa-solid fa-user-shield"></i></button>
                                <?php elseif ($button['type'] === 'history'): ?>
                                    <button type="button" class="btn-icon js-file-history" title="File History" aria-label="File History"
                                        data-bs-toggle="modal" data-bs-target="#fileHistoryModal"
                                        data-files="<?= html_escape(json_encode($button['files'])) ?>">
                                        <i class="fa-solid fa-clock-rotate-left"></i></button>
                                <?php elseif ($button['type'] === 'upload'): ?>
                                    <button type="button" class="btn-icon js-upload" title="Upload File" aria-label="Upload File"
                                        data-bs-toggle="modal" data-bs-target="#uploadModal"
                                        data-document-id="<?= (int) $dt_row['id'] ?>"
                                        data-document-name="<?= html_escape($dt_row['cells']['title'] ?? '') ?>"><i class="fa-solid fa-cloud-arrow-up"></i></button>
                                <?php elseif ($button['type'] === 'review'): ?>
                                    <a class="btn-icon" title="Review Controlled File" aria-label="Review Controlled File"
                                       target="_blank" rel="noopener"
                                       href="<?= site_url($button['url']) ?>"><i class="fa-solid fa-file-circle-check"></i></a>
                                <?php elseif ($button['type'] === 'download'): ?>
                                    <a class="btn-icon" title="Download File" aria-label="Download File" href="<?= site_url($button['url']) ?>"><i class="fa-solid fa-download"></i></a>
                                <?php elseif ($button['type'] === 'action'): ?>
                                    <button type="button" class="btn-icon js-action" title="<?= html_escape($button['label']) ?>" aria-label="<?= html_escape($button['label']) ?>"
                                        data-bs-toggle="modal" data-bs-target="#actionModal" data-url="<?= site_url($button['url']) ?>"
                                        data-id="<?= (int) $dt_row['id'] ?>" data-decision="<?= html_escape($button['decision'] ?? '') ?>"
                                        data-disposal="<?= !empty($button['disposal'])?'yes':'no' ?>"
                                        data-title="<?= html_escape($button['label']) ?>" data-description="<?= html_escape($button['description'] ?? '') ?>"><i class="<?= html_escape($button['icon'] ?? 'fa-solid fa-check') ?>"></i></button>
                                <?php endif; ?>
                            <?php endforeach; ?>
