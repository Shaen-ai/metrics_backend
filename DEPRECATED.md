# rsync deploy deprecated

Production deployment moved to Kubernetes.

- **Cluster manifests:** [kubernetes/README.md](../kubernetes/README.md) (or `mebel/kubernetes/` in monorepo workspace)

This `deploy.sh` remains for emergency rollback to PM2 until K8s cutover is verified.
