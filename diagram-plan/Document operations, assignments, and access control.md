
sequenceDiagram
    autonumber
    actor User
    participant Auth as Account and Permissions
    participant Document as Document Service
    participant Files as Controlled File Service
    participant Category as Category Service
    participant Storage as Storage Service
    participant Assignment as Assignment Service
    participant Access as Access Grant Service
    participant Audit as Audit Service

    User->>Auth: Request document operation
    Auth->>Auth: Validate permission and document scope
    alt Unauthorized
        Auth-->>User: Deny operation
        Auth->>Audit: Record denial
    else Authorized
        alt Create or revise softcopy
            User->>Document: Submit softcopy details and controlled file
            Document->>Files: Validate and stage file
            Files-->>Document: File validation result
            Document->>Document: Create approved revision
            Document->>Files: Make approved file current
            Document->>Files: Mark older approved files superseded
            Document->>Audit: Record revision and file history
        else Create or update hardcopy
            User->>Document: Submit hardcopy details
            Document->>Storage: Validate location and retention details
            Storage-->>Document: Validation result
            Document->>Document: Save hardcopy and holder details
            Document->>Audit: Record hardcopy changes
        else Cancel softcopy
            User->>Document: Request cancellation
            Document->>Document: Mark softcopy cancelled
            Document->>Files: Retire controlled files
            Document->>Audit: Record cancellation
        else Dispose document
            User->>Document: Submit disposal and reason
            Document->>Document: Record previous state and disposal history
            alt Hardcopy disposal through approval
                Document->>Storage: Clear location
                Document->>Access: Revoke document access grants
            else Direct softcopy disposal
                Document->>Files: Retire disposed files
            else Direct hardcopy disposal
                Document->>Document: Preserve disposal history
            end
            Document->>Audit: Record disposal
        else Assign document
            User->>Assignment: Select assignment mode
            alt Individual document
                Assignment->>Document: Determine document type
            else Softcopy category
                User->>Category: Select active category and user
                Category->>Category: Resolve descendants
                Category-->>Assignment: Active softcopies in category tree
                Assignment->>Assignment: Create or reactivate selected user's assignments
                Note over Assignment: Preserve assignments for other users
            else Hardcopy location
                User->>Storage: Select location and active user
                Storage->>Storage: Find active hardcopies at location
                Storage->>Storage: Reject open transfers
                Storage-->>Assignment: Eligible hardcopies
                Assignment->>Document: Update hardcopy holder
            end
            Assignment->>Audit: Record assignment changes
        else Grant document access
            User->>Access: Grant access with future expiry
            Access->>Auth: Validate target user
            Access->>Access: Validate expiry and document state
            Access-->>User: Confirm grant or reject
            Access->>Audit: Record access grant outcome
        end
    end