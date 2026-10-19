
sequenceDiagram
    autonumber
    actor User
    participant Auth as Account and Permissions
    participant Request as Request Service
    participant Workflow as Workflow Engine
    actor Approver
    participant Document as Document Service
    participant Audit as Audit Service

    User->>Auth: Sign in
    Auth->>Auth: Validate active account and password
    alt Invalid credentials
        Auth->>Audit: Record failed login attempt
        Auth-->>User: Reject login
    else Valid credentials
        opt Password change required
            Auth-->>User: Request password change
            User->>Auth: Submit new password
            Auth->>Auth: Validate and save password
        end
        Auth-->>User: Return authenticated user and roles
        User->>Auth: Request business action
        Auth->>Auth: Check permission and record scope
        alt Access denied
            Auth-->>User: Deny without changing records
            Auth->>Audit: Record denied action
        else Access permitted
            alt Request-based action
                User->>Request: Create request and proposed details
                Request-->>User: Save editable draft
                loop Until submitted or cancelled
                    alt Edit draft
                        User->>Request: Update draft
                        Request-->>User: Save changes
                    else Cancel draft
                        User->>Request: Cancel draft
                        Request->>Request: Retire pending attachment
                        Request->>Audit: Record cancellation
                    else Submit request
                        User->>Request: Submit request
                        Request->>Request: Validate ownership, permissions, document, recipient and attachment
                    end
                end
                Request->>Workflow: Check published workflow and proposal
                alt Validation fails
                    Workflow-->>Request: Report validation errors
                    Request-->>User: Keep editable draft
                else Validation succeeds
                    Workflow->>Workflow: Pin workflow version and create ordered steps
                    Workflow->>Request: Mark request submitted
                    Workflow-->>Approver: Notify current assigned approver
                    loop Until final approval, return or rejection
                        Approver->>Workflow: Submit approval decision
                        Workflow->>Workflow: Verify current assigned approver
                        alt Actor is not assigned
                            Workflow-->>Approver: Deny decision
                            Workflow->>Audit: Record denied action
                        else Actor is assigned
                            Workflow->>Request: Load proposal and decision history
                            Request-->>Approver: Show proposed details and pending attachment
                            alt Approve and more steps remain
                                Workflow->>Workflow: Activate immediately next step
                                Workflow-->>Approver: Notify next approver
                            else Final approval
                                Workflow->>Document: Validate current records and apply approved action
                                Document-->>Workflow: Return action result
                            else Return request
                                alt Current step is not first
                                    Workflow->>Workflow: Reactivate previous approver
                                    Workflow->>Workflow: Preserve prior decisions
                                    Workflow-->>Approver: Notify previous approver
                                else First approval step
                                    Workflow-->>User: Return request for correction
                                    User->>Request: Correct and resubmit
                                    Request->>Workflow: Reuse original workflow snapshot
                                end
                            else Reject request
                                Workflow->>Request: Mark request rejected
                                Request->>Request: Retire pending attachment
                                Workflow->>Audit: Record rejection
                            end
                        end
                    end
                end
            else Direct document action
                Auth->>Document: Execute permitted direct action
                Document->>Document: Validate current document
                Document-->>User: Return action result
            end
        end
        opt Sign out
            User->>Auth: Sign out
            Auth->>Auth: Invalidate session
            Auth->>Audit: Record sign-out event
        end
    end