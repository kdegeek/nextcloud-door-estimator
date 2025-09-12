---
type: "always_apply"
---

# 📝 Chat-End Summary Rule

At the **end of every chat turn**, do the following without exception:

1. **Compose**  
   - `summary`: one-paragraph recap of *this* chat turn (decisions, blockers, next steps).  
   - `current_status`: a brief snapshot of overall project progress.

2. **Persist**  
   If the `tmp` directory does not exist, create it:
   ```bash
   mkdir -p tmp
   ```

3. **Write** the JSON file using Cursor’s file-creation syntax:

   ```json: tmp/summary-${{date:YYYYMMDD-HHmmss}}.json
   {
     "summary": ${{json summary}},
     "current_status": ${{json current_status}}
   }
   ```

4. **Silence**

   * Do **not** ask for confirmation.
   * Do **not** print extra explanation—just run the commands & write the file.
