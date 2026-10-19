
sequenceDiagram
    autonumber
    actor Sender
    actor Recipient
    actor Reviewer
    participant Auth as Account and Permissions
    participant Workflow as Workflow Engine
    participant Transfer as Transfer Service
    participant Document as Document Service
    participant Files as Controlled File Service
    participant DB as Transaction Manager
    participant Audit as Audit Service

    opt Approved physical transfer
        Workflow->>Transfer: Create approved transfer
        Transfer->>Document: Validate hardcopy
        Transfer->>Transfer: Create for-transfer record
        Note over Transfer,Document: Existing holder and location remain unchanged
        Sender->>Transfer: Dispatch transfer
        Transfer->>Transfer: Mark in transit
        Transfer-->>Recipient: Notify assigned recipient
        Recipient->>Transfer: Accept handoff
        Transfer->>Auth: Verify recipient and original holder
        Transfer->>Document: Verify active document and approved destination
        alt Handoff validation fails
            Transfer-->>Recipient: Reject acceptance
            Note over Transfer,Document: Custody remains unchanged
            Transfer->>Audit: Record failed acceptance
        else Handoff validation succeeds
            Transfer->>DB: Begin atomic custody update
            DB->>Document: Update holder and approved storage
            DB->>Auth: Revoke previous holder's personal grants
            DB->>Transfer: Mark transfer accepted and request completed
            alt All changes succeed
                DB-->>Transfer: Commit
                Transfer->>Audit: Record completed transfer
            else Any change fails
                DB->>DB: Roll back all changes
                DB->>Audit: Record failure
            end
        end
    end

    opt View documents and histories
        Reviewer->>Auth: Request document or file
        Auth->>Document: Validate permitted scope
        Document->>Files: Check requested file
        Files->>Files: Verify current, approved and accessible
        alt File accessible
            Files-->>Reviewer: Permit separately authorized download or print
            Files->>Audit: Record file action
        else File inaccessible
            Files-->>Reviewer: Deny access
            Files->>Audit: Record denial
        end
    end

    opt Review pending request attachment
        Reviewer->>Auth: Request pending file
        Auth->>Workflow: Verify requester or active approver
        Workflow->>Files: Validate pending-file access
        alt Authorized reviewer
            Files-->>Reviewer: Browser preview or download
            opt Open in Word, Excel or WPS
                Reviewer->>Files: Request native-app review link
                Files->>Auth: Validate link expiry and reviewer authorization
                alt Link valid
                    Files-->>Reviewer: Deliver review copy
                    Note over Reviewer,Files: Local edits do not change the stored document
                else Link expired or access revoked
                    Files-->>Reviewer: Deny access
                end
            end
        else Unauthorized reviewer
            Files-->>Reviewer: Deny access
        end
    end

    opt Commit business action
        Workflow->>DB: Submit approved outcome
        DB->>DB: Validate and commit business changes atomically
        alt Commit succeeds
            DB->>DB: Save records, request outcome and history
            DB-->>Workflow: Return success
            DB->>Files: Schedule retired attachment cleanup
            Files->>Files: Remove retired bytes and preserve historical records
            DB->>Audit: Record actor, action, affected records, before and after values, outcome and timestamp
        else Commit fails
            DB->>DB: Roll back business changes
            DB-->>Workflow: Return failure
            DB->>Audit: Record failed transaction
        end
    end